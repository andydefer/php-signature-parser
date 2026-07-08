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
    public function __construct(
        public readonly Associative $data,
        public readonly StringTypedCollection $signature,
        public readonly StringTypedCollection $query,
    ) {}
}
