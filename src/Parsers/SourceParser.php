<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

final class SourceParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $source = $signature[0] ?? '';

        array_shift($signature);
        array_shift($query);

        return ParsedResultRecord::from([
            'data' => ['source' => $source],
            'signature' => $signature,
            'query' => $query,
        ]);
    }
}
