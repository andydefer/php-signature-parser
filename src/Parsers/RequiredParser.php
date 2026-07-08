<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class RequiredParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $required = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            $isRequired = $this->isRequiredArgument($element);

            if ($isRequired) {
                $required[$element] = $query[$queryIndex] ?? '';
                $queryIndex++;
            } else {
                $newSignature[] = $element;
            }
        }

        while ($queryIndex < $queryCount) {
            $newQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['required' => $required],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        // Récupérer UNIQUEMENT les arguments requis
        $requireds = [];
        foreach ($signature as $element) {
            if ($this->isRequiredArgument($element)) {
                $requireds[] = $element;
            }
        }

        if (empty($requireds)) {
            return new ValidationResultRecord(
                isValid: true,
                errors: $errors,
                suggestions: $suggestions
            );
        }

        // Extraire les valeurs de la query (uniquement les arguments, pas les flags/variadiques)
        $providedValues = [];
        $queryCount = count($query);
        $queryIndex = 0;

        // Parcourir la query et prendre uniquement les valeurs qui sont des arguments
        while ($queryIndex < $queryCount) {
            $current = $query[$queryIndex];

            // Stop at flag or variadic
            if (str_starts_with($current, '--') ||
                (str_starts_with($current, '[') && str_ends_with($current, ']'))) {
                break;
            }

            // Si c'est un argument simple (pas de =, *, ?)
            if (! str_contains($current, '=') &&
                ! str_contains($current, '*') &&
                ! str_ends_with($current, '?')) {
                $providedValues[] = $current;
            }

            $queryIndex++;
        }

        // Récupérer les arguments manquants
        $missing = [];
        $providedCount = count($providedValues);

        // Si le nombre de valeurs fournies est inférieur au nombre de requis
        if ($providedCount < count($requireds)) {
            // Prendre les arguments requis qui n'ont pas été fournis
            for ($i = $providedCount; $i < count($requireds); $i++) {
                if (isset($requireds[$i])) {
                    $missing[] = $requireds[$i];
                }
            }
        }

        // Ajouter les erreurs pour chaque argument manquant
        foreach ($missing as $arg) {
            $errors->add("Missing required argument: '{$arg}'");
            $suggestions->add("Provide a value for '{$arg}'");
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    /**
     * Détermine si un élément est un argument requis.
     */
    private function isRequiredArgument(string $element): bool
    {
        return ! str_contains($element, '=') &&
               ! str_contains($element, '*') &&
               ! str_ends_with($element, '?') &&
               ! str_starts_with($element, '--');
    }
}
