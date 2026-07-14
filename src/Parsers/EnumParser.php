<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Enums\ValueState;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Extracts enum arguments from a signature using ::name->[value1,value2,value3]=default syntax.
 *
 * Syntax:
 * - ::name->[value1,value2,value3]=*  → Required enum (must be provided)
 * - ::name->[value1,value2,value3]=?  → Optional enum (can be null with '_')
 * - ::name->[value1,value2,value3]=default → Enum with default value
 *
 * @example
 * Signature: 'set-level ::level->[beginner,middle,master]=*'
 * Query: 'set-level master'
 * Result: ['enums' => ['level' => ['value' => 'master', 'allowed_values' => [...], ...]]]
 * @example
 * Signature: 'set-level ::level->[beginner,middle,master]=?'
 * Query: 'set-level _'
 * Result: ['enums' => ['level' => ['value' => null, ...]]]
 * @example
 * Signature: 'set-level ::level->[beginner,middle,master]=middle'
 * Query: 'set-level'
 * Result: ['enums' => ['level' => ['value' => 'middle', ...]]]
 */
final class EnumParser implements ParserInterface
{
    private const REQUIRED = '*';

    private const OPTIONAL = '?';

    private const ENUM_PREFIX = '::';

    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $enumData = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;

        foreach ($signature as $token) {
            if ($this->isEnumToken($token)) {
                [$name, $allowedValues, $defaultValue, $isRequired, $isOptional] = $this->parseEnumToken($token);

                $value = null;
                $valueState = ValueState::DEFAULTED;
                $consumed = false;

                // Vérifier si la valeur est dans la requête
                if (isset($query[$queryIndex])) {
                    $queryToken = $query[$queryIndex];

                    // Skip token (_) => null (pour nullable)
                    if ($queryToken === '_' && $isOptional) {
                        $value = null;
                        $valueState = ValueState::OPTIONAL;
                        $queryIndex++;
                        $consumed = true;
                    } elseif (in_array($queryToken, $allowedValues, true)) {
                        $value = $queryToken;
                        $valueState = $isRequired ? ValueState::REQUIRED : ValueState::DEFAULTED;
                        $queryIndex++;
                        $consumed = true;
                    }
                }

                // Si pas consommé et required, c'est une erreur (sera capturée par validate)
                if (! $consumed && $isRequired) {
                    $value = null;
                    $valueState = ValueState::REQUIRED;
                }

                // Si pas consommé et pas required, utiliser la valeur par défaut
                if (! $consumed && ! $isRequired) {
                    $value = $defaultValue;
                    $valueState = ValueState::DEFAULTED;
                }

                $enumData[$name] = [
                    'value' => $value,
                    'allowed_values' => $allowedValues,
                    'default_value' => $defaultValue,
                    'value_state' => $valueState,
                ];

                // On ne garde pas le token dans la signature, il est consommé
            } else {
                $newSignature[] = $token;

                if (isset($query[$queryIndex])) {
                    $newQuery[] = $query[$queryIndex];
                    $queryIndex++;
                }
            }
        }

        // Ajouter les tokens de requête restants
        for ($i = $queryIndex; $i < count($query); $i++) {
            $newQuery[] = $query[$i];
        }

        return ParsedResultRecord::from([
            'data' => ['enums' => $enumData],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $queryIndex = 0;

        foreach ($signature as $token) {
            if ($this->isEnumToken($token)) {
                [$name, $allowedValues, $defaultValue, $isRequired, $isOptional] = $this->parseEnumToken($token);

                // Vérifier que les valeurs autorisées ne sont pas vides
                if (empty($allowedValues)) {
                    $errors->add("Enum '{$name}' has no allowed values");
                    $suggestions->add("Provide at least one allowed value: ::{$name}->[value1,value2,...]");

                    continue;
                }

                $allowedList = implode(', ', $allowedValues);

                // Vérifier la valeur par défaut
                if ($defaultValue !== null && ! in_array($defaultValue, $allowedValues, true)) {
                    $errors->add("Default value '{$defaultValue}' for enum '{$name}' is not in allowed values: {$allowedList}");
                    $suggestions->add("Use one of the allowed values: {$allowedList}");
                }

                // Vérifier la valeur dans la requête si présente
                if (isset($query[$queryIndex])) {
                    $queryToken = $query[$queryIndex];

                    // Skip token (_) est valide uniquement pour optional
                    if ($queryToken === '_') {
                        if (! $isOptional) {
                            $errors->add("Cannot use '_' for non-optional enum '{$name}'. Allowed: {$allowedList}");
                            $suggestions->add("Use one of: {$allowedList}");
                        }
                        $queryIndex++;

                        continue;
                    }

                    // Vérifier si la valeur est autorisée
                    if (! in_array($queryToken, $allowedValues, true)) {
                        $errors->add("Invalid value '{$queryToken}' for enum '{$name}'. Allowed: {$allowedList}");
                        $suggestions->add("Use one of: {$allowedList}".($isOptional ? " or '_' for null" : ''));
                    }

                    $queryIndex++;
                } else {
                    // Pas de valeur dans la requête
                    if ($isRequired) {
                        $errors->add("Missing required enum value for '{$name}'. Allowed: {$allowedList}");
                        $suggestions->add("Provide one of: {$allowedList}");
                    }
                }
            } else {
                if (isset($query[$queryIndex])) {
                    $queryIndex++;
                }
            }
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTokenPattern(): string
    {
        return '/^::[a-zA-Z_][a-zA-Z0-9_]*->\[[^\]]+\](?:=[^ ]+)?$/';
    }

    /**
     * Check if a token is an enum token.
     */
    private function isEnumToken(string $token): bool
    {
        return str_starts_with($token, self::ENUM_PREFIX)
            && str_contains($token, '->[')
            && str_contains($token, ']');
    }

    /**
     * Parse an enum token.
     *
     * @param  string  $token  The token (e.g., '::level->[beginner,middle,master]=middle')
     * @return array{0: string, 1: array<string>, 2: string|null, 3: bool, 4: bool}
     *                                                                              [name, allowedValues, defaultValue, isRequired, isOptional]
     */
    private function parseEnumToken(string $token): array
    {
        // Supprimer le préfixe '::'
        $tokenWithoutPrefix = substr($token, strlen(self::ENUM_PREFIX));

        // Extraire le nom avant ->
        $name = substr($tokenWithoutPrefix, 0, strpos($tokenWithoutPrefix, '->'));

        // Extraire la partie entre crochets
        $bracketContent = substr($tokenWithoutPrefix, strpos($tokenWithoutPrefix, '[') + 1);
        $bracketContent = substr($bracketContent, 0, strpos($bracketContent, ']'));

        // Extraire les valeurs autorisées - gérer le cas vide
        $allowedValues = [];
        if ($bracketContent !== '') {
            $allowedValues = array_map('trim', explode(',', $bracketContent));
            // Filtrer les valeurs vides
            $allowedValues = array_filter($allowedValues, fn ($v) => $v !== '');
            // Réindexer le tableau
            $allowedValues = array_values($allowedValues);
        }

        // Extraire la valeur par défaut (après =)
        $defaultValue = null;
        $isRequired = false;
        $isOptional = false;

        if (str_contains($tokenWithoutPrefix, '=')) {
            $defaultPart = substr($tokenWithoutPrefix, strrpos($tokenWithoutPrefix, '=') + 1);

            if ($defaultPart === self::REQUIRED) {
                $isRequired = true;
                $defaultValue = null;
            } elseif ($defaultPart === self::OPTIONAL) {
                $isOptional = true;
                $defaultValue = null;
            } else {
                $defaultValue = $defaultPart;
                $isOptional = false;
                $isRequired = false;
            }
        } else {
            // Pas de '=' => required par défaut
            $isRequired = true;
            $defaultValue = null;
        }

        return [$name, $allowedValues, $defaultValue, $isRequired, $isOptional];
    }
}
