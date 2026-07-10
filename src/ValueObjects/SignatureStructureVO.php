<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\SignatureParser;
use InvalidArgumentException;

/**
 * Represents the parsed structure of a CLI command signature.
 *
 * This value object analyzes a signature string to extract its components:
 * - Command name (source)
 * - Required arguments: {name}
 * - Default arguments: {name=value} (including nullables with value null)
 * - Variadic arguments: {name*}
 * - Boolean flags: {--flag}
 *
 * The structure is validated at construction time using the SignatureParser.
 *
 * @example
 * $structure = new SignatureStructureVO('backup {source} {destination} {format=zip} {env=?} {excludes*} {--force}');
 * $structure->getRequireds();      // ['source', 'destination']
 * $structure->getDefaults();       // ['format' => 'zip', 'env' => null]
 * $structure->getVariadics();      // ['excludes']
 * $structure->getFlags();          // ['force']
 */
final class SignatureStructureVO extends AbstractValueObject
{
    private string $source;

    /** @var array<string> */
    private array $required = [];

    /** @var array<string, string|null> */
    private array $default = [];

    /** @var array<string> */
    private array $variadic = [];

    /** @var array<string> */
    private array $flags = [];

    private string $rawSignature;

    private StrictDataObject $structure;

    private ValidationResultRecord $validationResult;

    /**
     * Parses and validates a command signature.
     *
     * @param  string  $signature  The CLI signature (e.g., 'greet {name} {--formal}')
     *
     * @throws InvalidArgumentException If the signature is empty or invalid
     */
    public function __construct(string $signature)
    {
        if ($signature === '') {
            throw new InvalidArgumentException('Signature cannot be empty');
        }

        $this->rawSignature = $signature;

        $parser = new SignatureParser;
        $this->validationResult = $parser->validateSignature($signature);

        $elements = $parser->extractSignatureElements($signature);

        $this->source = $elements[0] ?? '';

        $this->parseElements($elements->toArray());

        $this->structure = new StrictDataObject([
            'source' => $this->source,
            'required' => $this->required,
            'default' => $this->default,
            'variadic' => $this->variadic,
            'flags' => $this->flags,
        ]);
    }

    /**
     * Returns the command name (source).
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Returns the structured representation of the signature.
     */
    public function getStucture(): StrictDataObject
    {
        return $this->structure;
    }

    /**
     * Returns the list of required argument names.
     *
     * @return array<string>
     */
    public function getRequireds(): array
    {
        return $this->required;
    }

    /**
     * Returns the default arguments with their values.
     *
     * Nullable arguments ({name=?}) are represented with a null value.
     *
     * @return array<string, string|null>
     */
    public function getDefaults(): array
    {
        return $this->default;
    }

    /**
     * Returns the list of variadic argument names.
     *
     * @return array<string>
     */
    public function getVariadics(): array
    {
        return $this->variadic;
    }

    /**
     * Returns the list of flag names.
     *
     * @return array<string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Checks if a required argument exists.
     */
    public function hasRequired(string $name): bool
    {
        return in_array($name, $this->required, true);
    }

    /**
     * Checks if a default argument exists (including nullable).
     */
    public function hasDefault(string $name): bool
    {
        return array_key_exists($name, $this->default);
    }

    /**
     * Checks if a variadic argument exists.
     */
    public function hasVariadic(string $name): bool
    {
        return in_array($name, $this->variadic, true);
    }

    /**
     * Checks if a flag exists.
     */
    public function hasFlag(string $name): bool
    {
        return in_array($name, $this->flags, true);
    }

    /**
     * Checks if an argument exists (required, default, or variadic).
     */
    public function hasArgument(string $name): bool
    {
        return $this->hasRequired($name)
            || $this->hasDefault($name)
            || $this->hasVariadic($name);
    }

    /**
     * Returns the raw signature string.
     */
    public function getRaw(): string
    {
        return $this->rawSignature;
    }

    /**
     * Checks if the signature has any required arguments.
     */
    public function hasRequireds(): bool
    {
        return $this->required !== [];
    }

    /**
     * Checks if the signature has any default arguments (including nullable).
     */
    public function hasDefaults(): bool
    {
        return $this->default !== [];
    }

    /**
     * Checks if the signature has any variadic arguments.
     */
    public function hasVariadics(): bool
    {
        return $this->variadic !== [];
    }

    /**
     * Checks if the signature has any flags.
     */
    public function hasFlags(): bool
    {
        return $this->flags !== [];
    }

    /**
     * Returns whether the signature structure is valid.
     */
    public function isValid(): bool
    {
        return $this->validationResult->isValid;
    }

    /**
     * Returns validation errors if the signature is invalid.
     *
     * @return array<string>
     */
    public function getValidationErrors(): array
    {
        return $this->validationResult->errors->toArray();
    }

    /**
     * Returns validation suggestions for fixing errors.
     *
     * @return array<string>
     */
    public function getValidationSuggestions(): array
    {
        return $this->validationResult->suggestions->toArray();
    }

    /**
     * Returns the full validation result.
     */
    public function getValidationResult(): ValidationResultRecord
    {
        return $this->validationResult;
    }

    /**
     * {@inheritDoc}
     */
    public function getValue(): StrictDataObject
    {
        return $this->structure;
    }

    /**
     * {@inheritDoc}
     */
    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->rawSignature === $other->rawSignature;
    }

    /**
     * Parses the signature elements and populates component arrays.
     *
     * @param  array<int, string>  $elements  The extracted signature elements
     */
    private function parseElements(array $elements): void
    {
        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue;
            }

            if (str_starts_with($element, '--')) {
                $this->flags[] = ltrim($element, '--');

                continue;
            }

            if (str_contains($element, '*')) {
                $this->variadic[] = str_replace('*', '', $element);

                continue;
            }

            if (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);

                if ($defaultValue === '?') {
                    $this->default[$name] = null;
                } elseif ($defaultValue !== '') {
                    $this->default[$name] = $defaultValue;
                }

                continue;
            }

            $this->required[] = $element;
        }
    }
}
