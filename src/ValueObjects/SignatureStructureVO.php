<?php

// src/ValueObjects/SignatureStructureVO.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\SignatureParser;
use InvalidArgumentException;

/**
 * Value Object représentant la structure d'une signature CLI.
 *
 * Ce VO analyse UNIQUEMENT la signature (pas la requête) pour fournir
 * des informations sur sa structure : source, arguments requis, arguments
 * par défaut, variadiques et options.
 *
 * @example
 * $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*} {--force}');
 * $vo->getRequireds(); // ['source', 'destination']
 * $vo->getDefaults(); // ['format' => 'zip']
 * $vo->getVariadics(); // ['excludes']
 * $vo->getOptions(); // ['force']
 */
final class SignatureStructureVO extends AbstractValueObject
{
    private string $source;

    private array $required;

    private array $default;

    private array $variadic;

    private array $options;

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
        $this->options = [];

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue; // Skip source
            }

            if (str_starts_with($element, '--')) {
                $this->options[] = ltrim($element, '--');
            } elseif (str_contains($element, '*')) {
                $this->variadic[] = str_replace('*', '', $element);
            } elseif (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);
                $this->default[$name] = $defaultValue;
            } else {
                $this->required[] = $element;
            }
        }

        $this->structure = new StrictDataObject([
            'source' => $this->source,
            'required' => $this->required,
            'default' => $this->default,
            'variadic' => $this->variadic,
            'options' => $this->options,
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

    public function getOptions(): array
    {
        return $this->options;
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

    public function hasOption(string $name): bool
    {
        return in_array($name, $this->options, true);
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

    public function hasOptions(): bool
    {
        return ! empty($this->options);
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
