<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Parses required arguments from a command signature.
 *
 * Required arguments are defined without special characters (`=`, `*`, `--`).
 */
final class RequiredParser implements ParserInterface
{
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
            $isRequired = ! str_contains($element, '=') &&
                ! str_contains($element, '*') &&
                ! str_starts_with($element, '--');

            if ($isRequired) {
                $required[$element] = $query[$queryIndex] ?? '';
                $queryIndex++;
            } else {
                $remainingSignature[] = $element;
            }
        }

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
}
