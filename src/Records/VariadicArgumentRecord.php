<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Record representing a variadic argument with its name and array of values.
 */
final class VariadicArgumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly StringTypedCollection $values,
        public readonly StringTypedCollection $restrictions = new StringTypedCollection,
        public readonly ?string $comment = null,
    ) {}
}
