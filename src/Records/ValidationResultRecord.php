<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Record representing the result of a signature validation.
 *
 * Contains the validation status, error messages, and suggestions
 * for fixing the validation errors.
 */
final class ValidationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $isValid,
        public readonly StringTypedCollection $errors,
        public readonly StringTypedCollection $suggestions,
    ) {}
}
