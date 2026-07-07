<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Defines the contract for a single parser in the chain of responsibility.
 *
 * Each parser extracts specific elements from the signature and query,
 * then passes the remaining elements to the next parser.
 */
interface ParserInterface
{
    /**
     * Parses the signature and query elements.
     *
     * Each parser extracts what it handles and returns a ParsedResultRecord
     * containing the extracted data and the remaining elements for the next parser.
     *
     * @param  array<int, string>  $signature  The remaining signature elements
     * @param  array<int, string>  $query  The remaining query elements
     * @return ParsedResultRecord The parsed result with remaining elements
     */
    public function parse(array $signature, array $query): ParsedResultRecord;
}
