<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Parses default value arguments and nullable arguments from a command signature.
 *
 * Handles two syntaxes:
 * - `{name=value}`: Default value when no query value is provided
 * - `{name?}`: Nullable argument (value can be null)
 */
final class DefaultAndNullableParser implements ParserInterface
{
    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $defaults = [];
        $remainingSignature = [];
        $remainingQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if ($this->isDefaultOrNullable($element)) {
                [$name, $defaultValue] = $this->extractParameterInfo($element);

                $value = $defaultValue;
                $found = false;

                $queryValue = $this->findNextQueryValue($query, $queryIndex, $queryCount, $found, $queryIndex);

                if ($found && $queryValue !== null) {
                    $defaults[$name] = $queryValue;
                } elseif (! $found && $defaultValue !== null) {
                    $defaults[$name] = $defaultValue;
                } elseif ($queryValue === null && str_ends_with($element, '?')) {
                    $defaults[$name] = null;
                }
            } else {
                $remainingSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $remainingQuery[] = $query[$queryIndex];
                    $queryIndex++;
                } else {
                    $remainingQuery[] = '';
                }
            }
        }

        while ($queryIndex < $queryCount) {
            $remainingQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['default' => $defaults],
            'signature' => $remainingSignature,
            'query' => $remainingQuery,
        ]);
    }

    /**
     * Determines if an element is a default or nullable argument.
     */
    private function isDefaultOrNullable(string $element): bool
    {
        return str_contains($element, '=') || str_ends_with($element, '?');
    }

    /**
     * Extracts parameter name and default value from an element.
     *
     * @return array{0: string, 1: string|null}
     */
    private function extractParameterInfo(string $element): array
    {
        $name = $element;
        $defaultValue = null;

        if (str_contains($element, '=')) {
            [$name, $defaultValue] = explode('=', $element, 2);
            $defaultValue = $defaultValue === '' ? null : $defaultValue;
        } elseif (str_ends_with($element, '?')) {
            $name = rtrim($element, '?');
        }

        return [$name, $defaultValue];
    }

    /**
     * Finds the next value in the query that is not a special token.
     *
     * @return string|null The found value or null if no value is available
     */
    private function findNextQueryValue(array $query, int $startIndex, int $queryCount, bool &$found, int &$queryIndex): ?string
    {
        $value = null;
        $found = false;

        for ($i = $startIndex; $i < $queryCount; $i++) {
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

        return $value;
    }
}
