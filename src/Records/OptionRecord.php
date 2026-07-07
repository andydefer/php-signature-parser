<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record representing a single option with its name and boolean value.
 */
final class OptionRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly bool $value,
    ) {}
}
