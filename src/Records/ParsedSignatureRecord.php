<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;

/**
 * Record representing the parsed result of a command signature and query.
 */
final class ParsedSignatureRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $source,
        public readonly ArgumentCollection $required,
        public readonly ArgumentCollection $default,
        public readonly VariadicArgumentCollection $variadic,
        public readonly FlagCollection $flags,
    ) {}
}
