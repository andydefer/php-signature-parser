<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\SignatureParser;
use InvalidArgumentException;

/**
 * Value Object representing the structure of a CLI signature.
 *
 * This VO analyzes ONLY the signature (not the query) to provide
 * information about its structure: source, required arguments, default
 * arguments, variadics and flags.
 *
 * @example
 * $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');
 * $vo->getRequireds(); // ['source', 'destination']
 * $vo->getDefaults(); // ['format' => 'zip']
 * $vo->getVariadics(); // ['excludes']
 * $vo->getFlags(); // ['force']
 */
final class SignatureStructureVO extends AbstractValueObject
{
    private string $source;

    private array $required;

    private array $default;

    private array $variadic;

    private array $flags;

    private string $raw;

    private StrictDataObject $structure;

    public function __construct(string $signature)
    {
        if (empty($signature)) {
            throw new InvalidArgumentException('Signature cannot be empty');
        }

        $this->raw = $signature;

        $parser = new SignatureParser;
        $elements = $parser->extractSignatureElements($signature);

        // La source est toujours à la position 0
        $this->source = $elements[0] ?? '';

        // Analyser les éléments restants
        $this->required = [];
        $this->default = [];
        $this->variadic = [];
        $this->flags = [];

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue; // Skip source
            }

            if (str_starts_with($element, '--')) {
                $this->flags[] = ltrim($element, '--');
            } elseif (str_contains($element, '*')) {
                $this->variadic[] = str_replace('*', '', $element);
            } elseif (str_contains($element, '=') || str_ends_with($element, '?')) {
                $name = $element;
                $defaultValue = null;

                if (str_contains($element, '=')) {
                    [$name, $defaultValue] = explode('=', $element, 2);
                    $defaultValue = $defaultValue === '' ? null : $defaultValue;
                } elseif (str_ends_with($element, '?')) {
                    $name = rtrim($element, '?');
                }

                if ($defaultValue !== null) {
                    $this->default[$name] = $defaultValue;
                }
            } else {
                $this->required[] = $element;
            }
        }

        $this->structure = new StrictDataObject([
            'source' => $this->source,
            'required' => $this->required,
            'default' => $this->default,
            'variadic' => $this->variadic,
            'flags' => $this->flags,
        ]);
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getRequireds(): array
    {
        return $this->required;
    }

    public function getDefaults(): array
    {
        return $this->default;
    }

    public function getVariadics(): array
    {
        return $this->variadic;
    }

    public function getFlags(): array
    {
        return $this->flags;
    }

    public function hasRequired(string $name): bool
    {
        return in_array($name, $this->required, true);
    }

    public function hasDefault(string $name): bool
    {
        return isset($this->default[$name]);
    }

    public function hasVariadic(string $name): bool
    {
        return in_array($name, $this->variadic, true);
    }

    public function hasFlag(string $name): bool
    {
        return in_array($name, $this->flags, true);
    }

    public function countArguments(): int
    {
        return count($this->required) + count($this->default) + count($this->variadic);
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function hasRequireds(): bool
    {
        return ! empty($this->required);
    }

    public function hasDefaults(): bool
    {
        return ! empty($this->default);
    }

    public function hasVariadics(): bool
    {
        return ! empty($this->variadic);
    }

    public function hasFlags(): bool
    {
        return ! empty($this->flags);
    }

    public function getValue(): StrictDataObject
    {
        return $this->structure;
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->raw === $other->raw;
    }
}
