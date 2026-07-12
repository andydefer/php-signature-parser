<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;
use InvalidArgumentException;

final class VariadicParser implements ParserInterface
{
    private const PATTERN_RESTRICTED = '/^([a-zA-Z_][a-zA-Z0-9_]*)\*>\s*\[([^\]]*)\]\s*$/';

    private const PATTERN_SIMPLE = '/^[a-zA-Z_][a-zA-Z0-9_]*\*$/';

    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $variadics = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            // Check for restricted variadic: {name*>[value1,value2]}
            if (preg_match(self::PATTERN_RESTRICTED, $element, $matches)) {
                $name = $matches[1];
                $allowedString = trim($matches[2]);
                $allowed = $allowedString !== '' ? StringTypedCollection::from(array_map('trim', explode(',', $allowedString))) : new StringTypedCollection;

                $values = $this->extractVariadicValues($query, $queryIndex);

                // Advance index past the variadic token
                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    if (str_starts_with($query[$i], '[') && str_ends_with($query[$i], ']')) {
                        $queryIndex = $i + 1;
                        break;
                    }
                    $queryIndex++;
                }

                // Validate values against restrictions
                if ($allowed->isNotEmpty()) {
                    foreach ($values as $value) {
                        if (! $allowed->contains($value)) {
                            throw new InvalidArgumentException(
                                sprintf(
                                    'Value "%s" not allowed for "%s". Allowed values: %s',
                                    $value,
                                    $name,
                                    implode(', ', $allowed->toArray())
                                )
                            );
                        }
                    }
                }

                $variadics[] = new VariadicArgumentRecord(
                    name: $name,
                    values: StringTypedCollection::from($values),
                    restrictions: $allowed
                );

                continue;
            }

            // Simple variadic (existing logic)
            if (str_contains($element, '*')) {
                $name = str_replace('*', '', $element);
                $values = [];

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];

                    if (str_starts_with($current, '--')) {
                        break;
                    }

                    if (str_starts_with($current, '[') && str_ends_with($current, ']')) {
                        $content = trim($current, '[]');

                        if (! empty($content)) {
                            $parts = array_map('trim', explode(',', $content));

                            foreach ($parts as $part) {
                                if (! empty($part)) {
                                    $values[] = $part;
                                }
                            }
                        }

                        $queryIndex = $i + 1;
                        break;
                    }
                }

                $variadics[] = new VariadicArgumentRecord(
                    name: $name,
                    values: StringTypedCollection::from($values)
                );
            } else {
                $newSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $newQuery[] = $query[$queryIndex];
                    $queryIndex++;
                }
            }
        }

        while ($queryIndex < $queryCount) {
            $newQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        // Retourner un tableau simple [name => values] pour buildRecord()
        return ParsedResultRecord::from([
            'data' => ['variadics' => $this->buildVariadicArray($variadics)],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    /**
     * Builds the variadic array from VariadicArgumentRecord objects.
     *
     * @param  array<VariadicArgumentRecord>  $variadics
     * @return array<string, array<string>>
     */
    private function buildVariadicArray(array $variadics): array
    {
        $result = [];
        foreach ($variadics as $variadic) {
            $result[$variadic->name] = $variadic->values->toArray();
        }

        return $result;
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $variadicDefinitions = [];
        $restrictedDefinitions = [];
        $variadicOrder = [];

        foreach ($signature as $index => $element) {
            // Check for restricted variadic
            if (preg_match(self::PATTERN_RESTRICTED, $element, $matches)) {
                $name = $matches[1];
                $allowedString = trim($matches[2]);
                $allowed = $allowedString !== '' ? array_map('trim', explode(',', $allowedString)) : [];
                $restrictedDefinitions[$name] = $allowed;
                $variadicDefinitions[] = $name;
                $variadicOrder[] = ['name' => $name, 'restricted' => true, 'allowed' => $allowed];

                continue;
            }

            // Simple variadic
            if (str_contains($element, '*')) {
                $name = str_replace('*', '', $element);
                $variadicDefinitions[] = $name;
                $variadicOrder[] = ['name' => $name, 'restricted' => false, 'allowed' => []];
            }
        }

        $hasVariadic = ! empty($variadicDefinitions);

        // Extraire tous les tokens variadiques de la query dans l'ordre
        $variadicTokens = [];
        foreach ($query as $element) {
            if (str_starts_with($element, '[') && str_ends_with($element, ']')) {
                $variadicTokens[] = $element;
            }
        }

        $hasVariadicInQuery = ! empty($variadicTokens);

        if ($hasVariadicInQuery && ! $hasVariadic) {
            $errors->add('Variadic argument provided but not defined in signature');
            $suggestions->add('Add a variadic argument (*) to the signature');
        }

        if ($hasVariadic && ! $hasVariadicInQuery) {
            $suggestions->add('Variadic argument is defined but not used. Use [value1, value2] format');
        }

        // Valider chaque variadic individuellement dans l'ordre
        foreach ($variadicOrder as $index => $variadicDef) {
            if (! isset($variadicTokens[$index])) {
                // Pas de token pour ce variadic, c'est OK (optionnel)
                continue;
            }

            $token = $variadicTokens[$index];
            $content = trim($token, '[]');

            if (empty($content)) {
                // Token vide, c'est OK
                continue;
            }

            $parts = array_map('trim', explode(',', $content));

            // Vérifier les valeurs vides
            foreach ($parts as $part) {
                if (empty($part)) {
                    $errors->add('Empty value in variadic argument');
                    $suggestions->add('Remove empty values from the variadic list');
                    break 2;
                }
            }

            // Valider les restrictions UNIQUEMENT pour ce variadic
            if ($variadicDef['restricted']) {
                $name = $variadicDef['name'];
                $allowed = $variadicDef['allowed'];

                if (empty($allowed)) {
                    $errors->add("Restricted variadic '{$name}' has no allowed values");
                    $suggestions->add('Define at least one allowed value: {name*>[value1,value2]}');

                    continue;
                }

                foreach ($parts as $part) {
                    if (! empty($part) && ! in_array($part, $allowed, true)) {
                        $errors->add(
                            sprintf(
                                "Value '%s' not allowed for '%s'. Allowed: %s",
                                $part,
                                $name,
                                implode(', ', $allowed)
                            )
                        );
                        $suggestions->add(
                            sprintf(
                                "Allowed values for '%s': %s",
                                $name,
                                implode(', ', $allowed)
                            )
                        );
                    }
                }
            }
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    public function getTokenPattern(): string
    {
        return '/^([a-zA-Z_][a-zA-Z0-9_]*\*|([a-zA-Z_][a-zA-Z0-9_]*)\*>\s*\[[^\]]*\]\s*)$/';
    }

    /**
     * Extract variadic values from query at given index.
     *
     * @param  array<int, string>  $query
     * @return array<string>
     */
    private function extractVariadicValues(array $query, int $startIndex): array
    {
        $values = [];
        $queryCount = count($query);

        for ($i = $startIndex; $i < $queryCount; $i++) {
            $current = $query[$i];

            if (str_starts_with($current, '[') && str_ends_with($current, ']')) {
                $content = trim($current, '[]');
                if (! empty($content)) {
                    $parts = array_map('trim', explode(',', $content));
                    foreach ($parts as $part) {
                        if (! empty($part)) {
                            $values[] = $part;
                        }
                    }
                }
                break;
            }

            // Stop if we hit a flag
            if (str_starts_with($current, '--')) {
                break;
            }
        }

        return $values;
    }
}
