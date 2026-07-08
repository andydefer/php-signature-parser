<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class VariadicParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $variadic = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
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

                $variadic[$name] = $values;
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

        return ParsedResultRecord::from([
            'data' => ['variadic' => $variadic],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $variadicNames = [];
        foreach ($signature as $element) {
            if (str_contains($element, '*')) {
                $variadicNames[] = str_replace('*', '', $element);
            }
        }

        $hasVariadic = ! empty($variadicNames);
        $hasVariadicInQuery = false;

        foreach ($query as $element) {
            if (str_starts_with($element, '[') && str_ends_with($element, ']')) {
                $hasVariadicInQuery = true;
                break;
            }
        }

        if ($hasVariadicInQuery && ! $hasVariadic) {
            $errors->add('Variadic argument provided but not defined in signature');
            $suggestions->add('Add a variadic argument (*) to the signature');
        }

        if ($hasVariadic && ! $hasVariadicInQuery) {
            $suggestions->add('Variadic argument is defined but not used. Use [value1, value2] format');
        }

        // Validate variadic format
        foreach ($query as $element) {
            if (str_starts_with($element, '[') && str_ends_with($element, ']')) {
                $content = trim($element, '[]');

                if (! empty($content)) {
                    $parts = array_map('trim', explode(',', $content));

                    foreach ($parts as $part) {
                        if (empty($part)) {
                            $errors->add('Empty value in variadic argument');
                            $suggestions->add('Remove empty values from the variadic list');
                            break;
                        }
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
        return '/^[a-zA-Z_][a-zA-Z0-9_]*\*$/';
    }
}
