<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Parses nullable arguments from a command signature.
 * Handles syntax: {name?}
 */
final class NullableParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $nullables = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_ends_with($element, '?')) {
                $name = rtrim($element, '?');
                $found = false;
                $value = null;

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

                    if ($current === '' && ! str_starts_with($current, '[') && ! str_starts_with($current, '--')) {
                        $found = true;
                        $value = null;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                if ($found) {
                    $nullables[$name] = $value;
                } else {
                    $nullables[$name] = null;
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

        while ($queryIndex < $queryCount) {
            $newQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['nullable' => $nullables],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }
}
