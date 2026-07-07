<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

final class TextValueVO extends AbstractValueObject
{
    private string $value;

    public function __construct(string $token)
    {
        $this->value = str_replace('^', ' ', $token);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->value === $other->value;
    }

    public function getRaw(): string
    {
        return str_replace(' ', '^', $this->value);
    }
}
