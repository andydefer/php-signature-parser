<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

final class RequiredParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $required = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;

        foreach ($signature as $element) {
            if (! str_contains($element, '=') &&
                ! str_contains($element, '*') &&
                ! str_starts_with($element, '--')) {
                $required[$element] = $query[$queryIndex] ?? '';
                $queryIndex++;
            } else {
                $newSignature[] = $element;
                $newQuery[] = $query[$queryIndex] ?? '';
                $queryIndex++;
            }
        }

        return ParsedResultRecord::from([
            'data' => ['required' => $required],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }
}
