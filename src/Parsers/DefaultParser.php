<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class DefaultParser implements ParserInterface
{
    private const SKIP_TOKEN = '~';

    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $defaults = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            $isDefault = str_contains($element, '=');
            $isNullable = str_ends_with($element, '?');

            if ($isDefault || $isNullable) {
                $name = $element;
                $defaultValue = null;

                if ($isDefault) {
                    [$name, $defaultValue] = explode('=', $element, 2);

                    if ($defaultValue === '') {
                        throw new \InvalidArgumentException(
                            "Default argument '{$name}' cannot have empty value. Use '{$name}=?' for nullable instead."
                        );
                    }
                } elseif ($isNullable) {
                    $nameWithoutQuestion = rtrim($element, '?');
                    throw new \InvalidArgumentException(
                        "Invalid syntax '{$element}'. Use '{$nameWithoutQuestion}=?' for nullable instead."
                    );
                }

                $value = $defaultValue;
                $found = false;

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];

                    if (str_starts_with($current, '[') || str_starts_with($current, '--')) {
                        break;
                    }

                    if ($current === self::SKIP_TOKEN) {
                        $found = true;
                        $queryIndex = $i + 1;
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

        while ($queryIndex < $queryCount) {
            $newQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => [
                'defaults' => $defaults,
            ],
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
                    $suggestions->add("Use '{$name}=?' for nullable instead of '{$name}='");
                }

                if ($defaultValue === '?') {
                    continue;
                }
            }

            if (str_ends_with($element, '?')) {
                $name = rtrim($element, '?');
                $errors->add("Invalid syntax '{$element}'");
                $suggestions->add("Use '{$name}=?' for nullable instead");
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
        return '/^[a-zA-Z_][a-zA-Z0-9_]*=(?:[^=]+|\?)$/';
    }
}
