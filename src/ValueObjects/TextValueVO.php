<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

/**
 * Value Object that normalizes text by replacing caret (^) characters with spaces.
 *
 * This VO is used to handle command arguments that contain spaces, where the user
 * has used '^' as a space placeholder to avoid shell escaping issues.
 *
 *
 * @example
 * $text = new TextValueVO('Hello^World');
 * echo $text->getValue(); // 'Hello World'
 * echo $text->getRaw();   // 'Hello^World'
 */
final class TextValueVO extends AbstractValueObject
{
    private string $normalized;

    public function __construct(string $token)
    {
        $this->normalized = str_replace('^', ' ', $token);
    }

    /**
     * Returns the normalized value with '^' replaced by spaces.
     */
    public function getValue(): string
    {
        return $this->normalized;
    }

    /**
     * Returns the raw input value with spaces replaced by '^'.
     */
    public function getRaw(): string
    {
        return str_replace(' ', '^', $this->normalized);
    }

    public function __toString(): string
    {
        return $this->normalized;
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->normalized === $other->normalized;
    }
}
