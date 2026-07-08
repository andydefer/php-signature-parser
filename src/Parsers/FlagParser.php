<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class FlagParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $flags = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_starts_with($element, '--')) {
                $name = ltrim($element, '--');
                $found = false;

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    if ($query[$i] === $element) {
                        $found = true;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                $flags[$name] = $found;
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
            'data' => ['flags' => $flags],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $expectedFlags = [];
        foreach ($signature as $element) {
            if (str_starts_with($element, '--')) {
                $expectedFlags[] = ltrim($element, '--');
            }
        }

        $providedFlags = [];
        foreach ($query as $element) {
            if (str_starts_with($element, '--')) {
                $flagName = ltrim($element, '--');

                // Skip empty flag names
                if (empty($flagName)) {
                    continue;
                }

                $providedFlags[] = $flagName;

                if (! in_array($flagName, $expectedFlags, true)) {
                    $errors->add("Unknown flag: '{$element}'");
                    $suggestions->add("Remove flag '{$element}' or add it to the signature as '{--{$flagName}}'");
                }
            }
        }

        // Check for duplicate flags
        $seen = [];
        foreach ($providedFlags as $flag) {
            if (in_array($flag, $seen, true)) {
                $errors->add("Duplicate flag: '--{$flag}'");
                $suggestions->add("Remove duplicate flag '--{$flag}'");
            }
            $seen[] = $flag;
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    public function getTokenPattern(): string
    {
        return '/^--[a-zA-Z_][a-zA-Z0-9_]*$/';
    }
}
