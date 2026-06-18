<?php

// src/Parsers/RequiredParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;

final class RequiredParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
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

        return [
            'result' => ['required' => $required],
            'signature' => $newSignature,
            'query' => $newQuery,
        ];
    }
}
