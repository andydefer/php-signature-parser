<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Parses default and nullable arguments from a command signature.
 *
 * Handles:
 * - `{name=value}`: Default value when no query value is provided
 * - `{name=?}`: Nullable argument (implicitly null)
 * - `~`: Skip argument, use default value or null
 *
 * Invalid syntaxes:
 * - `{name?}`: Invalid (throws exception)
 * - `{name=}`: Invalid (throws exception)
 */
final class DefaultParser implements ParserInterface
{
    private const SKIP_TOKEN = '~';

    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $defaults = [];
        $nullables = [];
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
                $isNullableArg = false;

                if ($isDefault) {
                    [$name, $defaultValue] = explode('=', $element, 2);

                    // {name=} → invalide
                    if ($defaultValue === '') {
                        throw new \InvalidArgumentException(
                            "Default argument '{$name}' cannot have empty value. Use '{$name}=?' for nullable instead."
                        );
                    }

                    // {name=?} → nullable
                    if ($defaultValue === '?') {
                        $isNullableArg = true;
                        $nullables[$name] = null;

                        continue;
                    }
                } elseif ($isNullable) {
                    // {name?} → invalide
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

                    // Si on trouve ~, on saute et on garde la valeur par défaut
                    if ($current === self::SKIP_TOKEN) {
                        $found = true;
                        $queryIndex = $i + 1;
                        // Si c'est nullable et qu'on a ~, on garde null
                        if ($isNullableArg) {
                            $nullables[$name] = null;
                        }
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
                    // Si c'est nullable, on met la valeur dans nullable
                    if ($isNullableArg) {
                        $nullables[$name] = $value;
                    } else {
                        $defaults[$name] = $value;
                    }
                } else {
                    // Si c'est nullable et qu'aucune valeur n'est fournie, on garde null
                    if ($isNullableArg) {
                        $nullables[$name] = null;
                    } else {
                        $defaults[$name] = $defaultValue;
                    }
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
                'default' => $defaults,
                'nullable' => $nullables,
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
}
