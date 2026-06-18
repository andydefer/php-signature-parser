<?php

// src/Parsers/SourceParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;

final class SourceParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
    {
        $source = $signature[0] ?? '';

        array_shift($signature);
        array_shift($query);

        return [
            'result' => ['source' => $source],
            'signature' => $signature,
            'query' => $query,
        ];
    }
}
