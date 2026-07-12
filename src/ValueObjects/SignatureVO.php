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
 * - Nullable arguments
 * - Enum arguments (::name->[values])
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
    private array $requireds = [];

    /**
     * @var array<string, string|null> Default arguments (name => value)
     */
    private array $defaults = [];

    /**
     * @var array<string, array<string>> Variadic arguments (name => array of values)
     */
    private array $variadics = [];

    /**
     * @var array<string, bool> Boolean flags (name => value)
     */
    private array $flags = [];

    /**
     * @var array<string, string> Enum arguments (name => value)
     */
    private array $enums = [];

    /**
     * @var array<string, string> Custom tags (key => value)
     */
    private array $custom_tags = [];

    private string $source;

    private StrictDataObject $parsed;

    private ValidationResultRecord $validation_result;

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
        return $this->requireds[$name] ?? null;
    }

    /**
     * Returns all required arguments.
     *
     * @return array<string, string> StrictAssociative array of argument names to values
     */
    public function getRequireds(): array
    {
        return $this->requireds;
    }

    /**
     * Returns the value of a default argument by name.
     *
     * @param  string  $name  The argument name
     * @return string|null The value or null if not found
     */
    public function getDefault(string $name): ?string
    {
        return $this->defaults[$name] ?? null;
    }

    /**
     * Returns all default arguments.
     *
     * @return array<string, string|null> StrictAssociative array of argument names to values
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * Returns the values of a variadic argument by name.
     *
     * @param  string  $name  The argument name
     * @return array<string> The array of values or empty array if not found
     */
    public function getVariadic(string $name): array
    {
        return $this->variadics[$name] ?? [];
    }

    /**
     * Returns all variadic arguments.
     *
     * @return array<string, array<string>> StrictAssociative array of argument names to value arrays
     */
    public function getVariadics(): array
    {
        return $this->variadics;
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
     * @return array<string, bool> StrictAssociative array of flag names to boolean values
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Returns the value of an enum argument by name.
     *
     * @param  string  $name  The enum name
     * @return string|null The value or null if not found
     */
    public function getEnum(string $name): ?string
    {
        return $this->enums[$name] ?? null;
    }

    /**
     * Returns all enum arguments.
     *
     * @return array<string, string> StrictAssociative array of enum names to values
     */
    public function getEnums(): array
    {
        return $this->enums;
    }

    /**
     * Returns the value of a custom tag by key.
     *
     * @param  string  $key  The tag key
     * @return string|null The tag value or null if not found
     */
    public function getCustom(string $key): ?string
    {
        return $this->custom_tags[$key] ?? null;
    }

    /**
     * Returns all custom tags.
     *
     * @return array<string, string> StrictAssociative array of tag keys to values
     */
    public function getCustoms(): array
    {
        return $this->custom_tags;
    }

    /**
     * Checks if a custom tag exists.
     *
     * @param  string  $key  The tag key
     * @return bool True if the tag exists, false otherwise
     */
    public function hasCustom(string $key): bool
    {
        return isset($this->custom_tags[$key]);
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
        return isset($this->requireds[$name]);
    }

    /**
     * Checks if a default argument exists.
     *
     * @param  string  $name  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasDefault(string $name): bool
    {
        return isset($this->defaults[$name]);
    }

    /**
     * Checks if a variadic argument exists.
     *
     * @param  string  $name  The argument name
     * @return bool True if the argument exists, false otherwise
     */
    public function hasVariadic(string $name): bool
    {
        return isset($this->variadics[$name]);
    }

    /**
     * Checks if an enum argument exists.
     *
     * @param  string  $name  The enum name
     * @return bool True if the enum exists, false otherwise
     */
    public function hasEnum(string $name): bool
    {
        return isset($this->enums[$name]);
    }

    /**
     * Checks if any custom tags exist.
     *
     * @return bool True if custom tags exist, false otherwise
     */
    public function hasCustoms(): bool
    {
        return $this->custom_tags !== [];
    }

    /**
     * Returns whether the query is valid against the signature.
     *
     * @return bool True if valid, false otherwise
     */
    public function isValid(): bool
    {
        return $this->validation_result->isValid;
    }

    /**
     * Returns validation errors if the query is invalid.
     *
     * @return StringTypedCollection List of validation error messages
     */
    public function getValidationErrors(): StringTypedCollection
    {
        return $this->validation_result->errors;
    }

    /**
     * Returns validation suggestions for fixing errors.
     *
     * @return StringTypedCollection List of suggestions
     */
    public function getValidationSuggestions(): StringTypedCollection
    {
        return $this->validation_result->suggestions;
    }

    /**
     * Returns the validation result as an object.
     *
     * @return ValidationResultRecord The validation result
     */
    public function getValidationResult(): ValidationResultRecord
    {
        return $this->validation_result;
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

        $this->requireds = [];
        foreach ($result->requireds as $arg) {
            $this->requireds[$arg->name] = $arg->value;
        }

        $this->defaults = [];
        foreach ($result->defaults as $arg) {
            $this->defaults[$arg->name] = $arg->value;
        }

        $this->variadics = [];
        foreach ($result->variadics as $arg) {
            $this->variadics[$arg->name] = $arg->values->toArray();
        }

        $this->flags = [];
        foreach ($result->flags as $flag) {
            $this->flags[$flag->name] = $flag->value;
        }

        $this->enums = [];
        foreach ($result->enums as $enum) {
            $this->enums[$enum->name] = $enum->value;
        }

        // Extract custom tags from custom_data
        $customData = $result->custom_data->toArray();
        foreach ($customData as $key => $value) {
            $this->custom_tags[$key] = $value;
        }

        $this->parsed = new StrictDataObject([
            'source' => $this->source,
            'requireds' => $this->requireds,
            'defaults' => $this->defaults,
            'variadics' => $this->variadics,
            'flags' => $this->flags,
            'enums' => $this->enums,
            'custom_tags' => $this->custom_tags,
        ]);
    }

    /**
     * Validates the query against the signature.
     */
    private function validate(): void
    {
        $parser = new SignatureParser;
        $this->validation_result = $parser->validate($this->signature, $this->query);
    }
}
