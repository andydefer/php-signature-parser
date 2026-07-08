<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
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

    /** @var array<string> */
    private array $required = [];

    /** @var array<string, string> */
    private array $default = [];

    /** @var array<string> */
    private array $variadic = [];

    /** @var array<string> */
    private array $flags = [];

    private string $raw;

    private StrictDataObject $structure;

    private ValidationResultRecord $validationResult;

    public function __construct(string $signature)
    {
        if (empty($signature)) {
            throw new InvalidArgumentException('Signature cannot be empty');
        }

        $this->raw = $signature;

        $parser = new SignatureParser;
        $this->validationResult = $parser->validateSignature($signature);

        $elements = $parser->extractSignatureElements($signature);

        $this->source = $elements[0] ?? '';

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue;
            }

            if (str_starts_with($element, '--')) {
                $this->flags[] = ltrim($element, '--');
            } elseif (str_contains($element, '*')) {
                $this->variadic[] = str_replace('*', '', $element);
            } elseif (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);
                if ($defaultValue !== '' && $defaultValue !== '?') {
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

    /**
     * @return array<string>
     */
    public function getRequireds(): array
    {
        return $this->required;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaults(): array
    {
        return $this->default;
    }

    /**
     * @return array<string>
     */
    public function getVariadics(): array
    {
        return $this->variadic;
    }

    /**
     * @return array<string>
     */
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

    /**
     * Returns whether the signature structure is valid.
     *
     * @return bool True if the signature is valid, false otherwise
     */
    public function isValid(): bool
    {
        return $this->validationResult->isValid;
    }

    /**
     * Returns validation errors if the signature is invalid.
     *
     * @return array<string> List of validation error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validationResult->errors->toArray();
    }

    /**
     * Returns validation suggestions for fixing errors.
     *
     * @return array<string> List of suggestions
     */
    public function getValidationSuggestions(): array
    {
        return $this->validationResult->suggestions->toArray();
    }

    /**
     * Returns the validation result as an object.
     *
     * @return ValidationResultRecord The validation result
     */
    public function getValidationResult(): ValidationResultRecord
    {
        return $this->validationResult;
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
