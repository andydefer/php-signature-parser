<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Extracts required arguments from a signature and query.
 *
 * Required arguments are simple tokens without '=', '*', '?', or '--' prefix.
 * They must appear in the query in the order they are defined.
 *
 * @example
 * Signature: '{source} {destination}'
 * Query: '/var/www /backup'
 * Result: ['source' => '/var/www', 'destination' => '/backup']
 */
final class RequiredParser implements ParserInterface
{
    private const PLACEHOLDER_MISSING = '~';

    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $required = [];
        $remainingSignature = [];
        $remainingQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if ($this->isRequiredArgument($element)) {
                $required[$element] = $query[$queryIndex] ?? '';
                $queryIndex++;
            } else {
                $remainingSignature[] = $element;
            }
        }

        // Preserve remaining query tokens for subsequent parsers
        while ($queryIndex < $queryCount) {
            $remainingQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['required' => $required],
            'signature' => $remainingSignature,
            'query' => $remainingQuery,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $requiredArguments = $this->extractRequiredArgumentNames($signature);

        if ($requiredArguments === []) {
            return new ValidationResultRecord(
                isValid: true,
                errors: $errors,
                suggestions: $suggestions
            );
        }

        $providedValues = $this->extractProvidedArgumentValues($query);

        $missingArguments = $this->findMissingRequiredArguments(
            $requiredArguments,
            $providedValues
        );

        foreach ($missingArguments as $argument) {
            $errors->add("Missing required argument: '{$argument}'");
            $suggestions->add("Provide a value for '{$argument}'");
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
        return '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    }

    /**
     * Determines if an element is a required argument.
     *
     * Required arguments are simple tokens without:
     * - '=' (default or nullable)
     * - '*' (variadic)
     * - '?' (nullable)
     * - '--' (flag)
     */
    private function isRequiredArgument(string $element): bool
    {
        return ! str_contains($element, '=')
            && ! str_contains($element, '*')
            && ! str_ends_with($element, '?')
            && ! str_starts_with($element, '--');
    }

    /**
     * Extracts the names of all required arguments from the signature.
     *
     * @param  array<int, string>  $signature  The signature tokens
     * @return array<int, string> The required argument names
     */
    private function extractRequiredArgumentNames(array $signature): array
    {
        $requireds = [];

        foreach ($signature as $element) {
            if ($this->isRequiredArgument($element)) {
                $requireds[] = $element;
            }
        }

        return $requireds;
    }

    /**
     * Extracts argument values from the query, ignoring flags and variadics.
     *
     * @param  array<int, string>  $query  The query tokens
     * @return array<int, string> The provided argument values
     */
    private function extractProvidedArgumentValues(array $query): array
    {
        $providedValues = [];
        $queryCount = count($query);
        $queryIndex = 0;

        while ($queryIndex < $queryCount) {
            $current = $query[$queryIndex];

            // Stop at flag or variadic
            if (str_starts_with($current, '--') ||
                (str_starts_with($current, '[') && str_ends_with($current, ']'))) {
                break;
            }

            // Skip the placeholder for missing values
            if ($current !== self::PLACEHOLDER_MISSING
                && ! str_contains($current, '=')
                && ! str_contains($current, '*')
                && ! str_ends_with($current, '?')) {
                $providedValues[] = $current;
            }

            $queryIndex++;
        }

        return $providedValues;
    }

    /**
     * Finds required arguments that are missing from the provided values.
     *
     * @param  array<int, string>  $requiredArguments  The required argument names
     * @param  array<int, string>  $providedValues  The provided argument values
     * @return array<int, string> The missing required argument names
     */
    private function findMissingRequiredArguments(
        array $requiredArguments,
        array $providedValues
    ): array {
        $missing = [];
        $providedCount = count($providedValues);

        if ($providedCount < count($requiredArguments)) {
            for ($i = $providedCount; $i < count($requiredArguments); $i++) {
                if (isset($requiredArguments[$i])) {
                    $missing[] = $requiredArguments[$i];
                }
            }
        }

        return $missing;
    }
}
