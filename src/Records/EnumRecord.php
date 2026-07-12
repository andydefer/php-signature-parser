<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Enums\ValueState;

/**
 * Record representing an enum argument.
 *
 * Contains the name, value, allowed values, default value,
 * and flags indicating if the enum is required or nullable.
 */
final class EnumRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
        public readonly StringTypedCollection $allowed_values,
        public readonly ?string $default_value = null,
        public readonly ValueState $value_state = ValueState::OPTIONAL,
    ) {}
}
