<?php

// src/ValueObjects/SignatureVO.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\SignatureParser;
use InvalidArgumentException;

final class SignatureVO extends AbstractValueObject
{
    private string $source;

    private array $required;

    private array $default;

    private array $variadic;

    private array $options;

    private StrictDataObject $parsed;

    public function __construct(
        private readonly string $signature,
        private readonly string $query
    ) {
        if (empty($this->signature)) {
            throw new InvalidArgumentException('Signature cannot be empty');
        }

        if (empty($this->query)) {
            throw new InvalidArgumentException('Query cannot be empty');
        }

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->source = $result->source;

        $this->required = [];
        foreach ($result->required as $arg) {
            $this->required[$arg->name] = $arg->value;
        }

        $this->default = [];
        foreach ($result->default as $arg) {
            $this->default[$arg->name] = $arg->value;
        }

        $this->variadic = [];
        foreach ($result->variadic as $arg) {
            $this->variadic[$arg->name] = $arg->values->toArray();
        }

        $this->options = [];
        foreach ($result->options as $opt) {
            $this->options[$opt->name] = $opt->value;
        }

        $this->parsed = new StrictDataObject([
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

    public function getRequired(string $name): ?string
    {
        return $this->required[$name] ?? null;
    }

    public function getRequireds(): array
    {
        return $this->required;
    }

    public function getDefault(string $name): ?string
    {
        return $this->default[$name] ?? null;
    }

    public function getDefaults(): array
    {
        return $this->default;
    }

    public function getVariadic(string $name): array
    {
        return $this->variadic[$name] ?? [];
    }

    public function getVariadics(): array
    {
        return $this->variadic;
    }

    public function getOption(string $name): bool
    {
        return $this->options[$name] ?? false;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getParsed(): StrictDataObject
    {
        return $this->parsed;
    }

    public function hasOption(string $name): bool
    {
        return ($this->options[$name] ?? false) === true;
    }

    public function hasRequired(string $name): bool
    {
        return isset($this->required[$name]);
    }

    public function hasDefault(string $name): bool
    {
        return isset($this->default[$name]);
    }

    public function hasVariadic(string $name): bool
    {
        return isset($this->variadic[$name]);
    }

    public function getValue(): StrictDataObject
    {
        return $this->parsed;
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->signature === $other->signature
            && $this->query === $other->query;
    }
}
