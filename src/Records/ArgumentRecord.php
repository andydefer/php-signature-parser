<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record representing a single argument with its name, value and optional comment.
 */
final class ArgumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value = null,
        public readonly ?string $comment = null,
    ) {}
}
