<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\Associative;

/**
 * Record representing the result of a signature parser operation.
 *
 * Contains the extracted data from a command signature and query,
 * along with the remaining elements to be processed by the next parser.
 */
final class ParsedResultRecord extends AbstractRecord
{
    /**
     * @param  Associative  $data  The extracted data from this parser (associative array with camelCase keys)
     * @param  StringTypedCollection  $signature  The remaining signature elements
     * @param  StringTypedCollection  $query  The remaining query elements
     */
    public function __construct(
        public readonly Associative $data,
        public readonly StringTypedCollection $signature,
        public readonly StringTypedCollection $query,
    ) {}
}
