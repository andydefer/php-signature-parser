<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Parses the command source (the command name) from a signature.
 *
 * The source is always the first element of the signature and query.
 */
final class SourceParser implements ParserInterface
{
    /**
     * {@inheritDoc}
     */
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
