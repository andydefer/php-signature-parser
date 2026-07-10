<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\SignatureParser;
use InvalidArgumentException;

/**
 * Value Object representing a complete command signature and query pair.
 *
 * Provides typed access to all parsed components of a CLI command:
 * - Source (command name)
 * - Required arguments
 * - Default arguments
 * - Variadic arguments
 * - Boolean flags
 * - Custom tags (<key="value">)
 *
 * Additionally, this VO validates the query against the signature and provides
 * validation results including errors and suggestions.
 *
 * @example
 * $vo = new SignatureVO('send {recipient} {--verbose}', 'send John --verbose <greeting="Hello World">');
 * $vo->getSource(); // 'send'
 * $vo->getRequired('recipient'); // 'John'
 * $vo->getFlag('verbose'); // true
 * $vo->getCustom('greeting'); // 'Hello World'
 */
final class SignatureVO extends AbstractValueObject
{
    /**
     * @var array<string, string> Required arguments (name => value)
     */
    private array $required = [];

    /**
     * @var array<string, string|null> Default arguments (name => value)
     */
    private array $default = [];

    /**
     * @var array<string, array<string>> Variadic arguments (name => array of values)
     */
    private array $variadic = [];

    /**
     * @var array<string, bool> Boolean flags (name => value)
     */
    private array $flags = [];

    /**
     * @var array<string, string> Custom tags (key => value)
     */
    private array $customTags = [];

    private StrictDataObject $parsed;

    private ValidationResultRecord $validationResult;

    /**
     * Constructs a new SignatureVO instance.
     *
     * The constructor parses the signature and query, then validates the query
     * against the signature. Use isValid() to check if the query is valid.
     *
     * @param  string  $signature  The command signature (e.g., 'backup {source} {--force}')
     * @param  string  $query  The actual command query (e.g., 'backup /var/www --force <greeting="Hello">')
     *
     * @throws InvalidArgumentException If the signature or query is empty
     */
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

        $this->parse();
        $this->validate();
    }

    /**
     * Returns the command name (source).
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Returns the value of a required argument by name.
     *
     * @param  string  $name  The argument name
     * @return string|null The value or null if not found
     */
    public function getRequired(string $name): ?string
    {
        return $this->required[$name] ?? null;
    }

    /**
     * Returns all required arguments.
     *
     * @return array<string, string> Associative array of argument names to values
     */
    public function getRequireds(): array
    {
        return $this->required;
    }

    /**
     * Returns the value of a default argument by name.
     *
     * @param  string  $name  The argument name
     * @return string|null The value or null if not found
     */
    public function getDefault(string $name): ?string
    {
        return $this->default[$name] ?? null;
    }

    /**
     * Returns all default arguments.
     *
     * @return array<string, string|null> Associative array of argument names to values
     */
    public function getDefaults(): array
    {
        return $this->default;
    }

    /**
     * Returns the values of a variadic argument by name.
     *
     * @param  string  $name  The argument name
     * @return array<string> The array of values or empty array if not found
     */
    public function getVariadic(string $name): array
    {
        return $this->variadic[$name] ?? [];
    }

    /**
     * Returns all variadic arguments.
     *
     * @return array<string, array<string>> Associative array of argument names to value arrays
     */
    public function getVariadics(): array
    {
        return $this->variadic;
    }

    /**
     * Returns the value of a flag by name.
     *
     * @param  string  $name  The flag name (without '--' prefix)
     * @return bool True if the flag is present, false otherwise
     */
    public function getFlag(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    /**
     * Returns all flags.
     *
     * @return array<string, bool> Associative array of flag names to boolean values
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Returns the value of a custom tag by key.
     *
     * @param  string  $key  The tag key
     * @return string|null The tag value or null if not found
     */
    public function getCustom(string $key): ?string
    {
        return $this->customTags[$key] ?? null;
    }

    /**
     * Returns all custom tags.
     *
     * @return array<string, string> Associative array of tag keys to values
     */
    public function getCustoms(): array
    {
        return $this->customTags;
    }

    /**
     * Checks if a custom tag exists.
     *
     * @param  string  $key  The tag key
     * @return bool True if the tag exists, false otherwise
     */
    public function hasCustom(string $key): bool
    {
        return isset($this->customTags[$key]);
    }

    /**
     * Returns the parsed structure as a StrictDataObject.
     */
    public function getParsed(): StrictDataObject
    {
        return $this->parsed;
    }

    /**
     * Checks if a flag is present and true.
     *
     * @param  string  $name  The flag name
     * @return bool True if the flag is set, false otherwise
     */
    public function hasFlag(string $name): bool
    {
        return ($this->flags[$name] ?? false) === true;
    }

    /**
     * Checks if a required argument exists.
     *
     * @param  string  $name  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasRequired(string $name): bool
    {
        return isset($this->required[$name]);
    }

    /**
     * Checks if a default argument exists.
     *
     * @param  string  $name  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasDefault(string $name): bool
    {
        return isset($this->default[$name]);
    }

    /**
     * Checks if a variadic argument exists.
     *
     * @param  string  $name  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasVariadic(string $name): bool
    {
        return isset($this->variadic[$name]);
    }

    /**
     * Checks if any custom tags exist.
     *
     * @return bool True if custom tags exist, false otherwise
     */
    public function hasCustoms(): bool
    {
        return $this->customTags !== [];
    }

    /**
     * Returns whether the query is valid against the signature.
     *
     * @return bool True if valid, false otherwise
     */
    public function isValid(): bool
    {
        return $this->validationResult->isValid;
    }

    /**
     * Returns validation errors if the query is invalid.
     *
     * @return StringTypedCollection List of validation error messages
     */
    public function getValidationErrors(): StringTypedCollection
    {
        return $this->validationResult->errors;
    }

    /**
     * Returns validation suggestions for fixing errors.
     *
     * @return StringTypedCollection List of suggestions
     */
    public function getValidationSuggestions(): StringTypedCollection
    {
        return $this->validationResult->suggestions;
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

    /**
     * {@inheritDoc}
     */
    public function getValue(): StrictDataObject
    {
        return $this->parsed;
    }

    /**
     * {@inheritDoc}
     */
    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->signature === $other->signature
            && $this->query === $other->query;
    }

    /**
     * Parses the signature and query using the SignatureParser.
     */
    private function parse(): void
    {
        $parser = new SignatureParser;
        $result = $parser->parse($this->signature, $this->query);

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

        $this->flags = [];
        foreach ($result->flags as $flag) {
            $this->flags[$flag->name] = $flag->value;
        }

        // Extract custom tags from custom_data
        $customData = $result->custom_data->toArray();
        foreach ($customData as $key => $value) {
            $this->customTags[$key] = $value;
        }

        $this->parsed = new StrictDataObject([
            'source' => $this->source,
            'required' => $this->required,
            'default' => $this->default,
            'variadic' => $this->variadic,
            'flags' => $this->flags,
            'custom_tags' => $this->customTags,
        ]);
    }

    /**
     * Validates the query against the signature.
     */
    private function validate(): void
    {
        $parser = new SignatureParser;
        $this->validationResult = $parser->validate($this->signature, $this->query);
    }

    private string $source;
}
