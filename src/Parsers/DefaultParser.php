<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Parses default value arguments from a command signature.
 *
 * Handles syntax: {name=value}
 */
final class DefaultParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $defaults = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);

                // {name=} avec valeur vide est interdit
                if ($defaultValue === '') {
                    throw new \InvalidArgumentException(
                        "Default argument '{$name}' cannot have empty value. Use '?' for nullable instead."
                    );
                }

                $value = $defaultValue;
                $found = false;

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];

                    if (str_starts_with($current, '[') || str_starts_with($current, '--')) {
                        break;
                    }

                    if (! empty($current) && ! str_starts_with($current, '[') && ! str_starts_with($current, '--')) {
                        $value = $current;
                        $found = true;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                if ($found) {
                    $defaults[$name] = $value;
                } else {
                    $defaults[$name] = $defaultValue;
                }
            } else {
                $newSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $newQuery[] = $query[$queryIndex];
                    $queryIndex++;
                } else {
                    $newQuery[] = '';
                }
            }
        }

        // Ajouter le reste de la query
        while ($queryIndex < $queryCount) {
            $newQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['default' => $defaults],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        foreach ($signature as $element) {
            if (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);

                if ($defaultValue === '') {
                    $errors->add("Default argument '{$name}' has empty value");
                    $suggestions->add("Use '?' for nullable instead of '='");
                }
            }
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }
}
