<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use InvalidArgumentException;

/**
 * Builds CLI query strings from a signature with type-safe argument handling.
 *
 * This builder allows constructing query strings by setting arguments and flags
 * with automatic validation against the signature structure. It supports:
 * - Required arguments
 * - Default arguments (with values)
 * - Nullable arguments (with null defaults)
 * - Variadic arguments
 * - Boolean flags
 * - Custom tags (<key="value">)
 *
 * @example
 * $builder = QueryBuilder::init('greet {name} {--formal}');
 * $query = $builder->setRequired('name', 'John')
 *                  ->setFlag('--formal', true)
 *                  ->setCustom('greeting', 'Hello World')
 *                  ->build();
 * // Result: 'greet John --formal <greeting="Hello World">'
 */
final class QueryBuilder
{
    private SignatureStructureVO $structure;

    /** @var array<string, string> */
    private array $arguments = [];

    /** @var array<string, bool> */
    private array $flags = [];

    /** @var array<string, string> */
    private array $customTags = [];

    private string $source;

    private bool $validated = false;

    private ValidationResultRecord $validationResult;

    /**
     * Initializes a new QueryBuilder with a signature.
     *
     * @param  string  $signature  The CLI signature (e.g., 'greet {name} {--formal}')
     * @param  string|null  $initialQuery  Optional initial query to populate the builder
     *
     * @throws InvalidArgumentException If the signature is invalid
     */
    public static function init(string $signature, ?string $initialQuery = null): self
    {
        return new self($signature, $initialQuery);
    }

    private function __construct(string $signature, ?string $initialQuery = null)
    {
        $structure = new SignatureStructureVO($signature);

        if (! $structure->isValid()) {
            $errors = $structure->getValidationErrors();
            throw new InvalidArgumentException(
                'Invalid signature: '.implode(', ', $errors)
            );
        }

        $this->structure = $structure;
        $this->source = $structure->getSource();

        // Initialize with default values
        foreach ($structure->getDefaults() as $name => $defaultValue) {
            $this->arguments[$name] = $defaultValue ?? '~';
        }

        foreach ($structure->getFlags() as $flag) {
            $this->flags['--'.$flag] = false;
        }

        if ($initialQuery !== null) {
            $this->parseInitialQuery($initialQuery);
        }

        $this->validated = false;
    }

    /**
     * Parses an initial query to populate the builder.
     */
    private function parseInitialQuery(string $query): self
    {
        $parser = new SignatureParser;
        $parsed = $parser->parse($this->structure->getRaw(), $query);

        foreach ($parsed->required as $arg) {
            $this->arguments[$arg->name] = $arg->value !== '' ? $arg->value : '~';
        }

        foreach ($parsed->default as $arg) {
            $this->arguments[$arg->name] = $arg->value !== '' ? $arg->value : '~';
        }

        foreach ($parsed->variadic as $arg) {
            $values = $arg->values->join(' ');
            $this->arguments[$arg->name] = $values !== '' ? $values : '~';
        }

        foreach ($parsed->flags as $flag) {
            $this->flags['--'.$flag->name] = $flag->value;
        }

        // Extract custom tags from the parsed data
        $customData = $parsed->custom_data->toArray();
        foreach ($customData as $key => $value) {
            $this->customTags[$key] = $value;
        }

        return $this;
    }

    /**
     * Sets an argument value with automatic type detection.
     *
     * This method automatically determines whether the argument is required,
     * default, or variadic and applies the appropriate validation.
     *
     * @param  string  $name  The argument name
     * @param  string|null  $value  The value (null resets to default)
     *
     * @throws InvalidArgumentException If the argument does not exist in the signature
     */
    public function setArgument(string $name, ?string $value = null): self
    {
        if (! $this->structure->hasArgument($name)) {
            throw new InvalidArgumentException(
                sprintf('Argument "%s" does not exist in signature', $name)
            );
        }

        if ($this->structure->hasRequired($name)) {
            // Required arguments cannot be null or empty
            if ($value === null || $value === '') {
                throw new InvalidArgumentException(
                    sprintf('Required argument "%s" cannot be null or empty', $name)
                );
            }
            $this->arguments[$name] = $value;
        } elseif ($this->structure->hasDefault($name)) {
            // Default arguments accept null and empty
            if ($value === null) {
                $defaults = $this->structure->getDefaults();
                $this->arguments[$name] = $defaults[$name] ?? '~';
            } elseif ($value === '') {
                $this->arguments[$name] = '~';
            } else {
                $this->arguments[$name] = $value;
            }
        } elseif ($this->structure->hasVariadic($name)) {
            // Variadic arguments
            if ($value === null || $value === '') {
                $this->arguments[$name] = '~';
            } else {
                $this->arguments[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Sets a value for a required argument.
     *
     * @param  string  $name  The argument name
     * @param  string  $value  The value
     *
     * @throws InvalidArgumentException If the argument does not exist or is not required
     */
    public function setRequired(string $name, string $value): self
    {
        if (! $this->structure->hasRequired($name)) {
            throw new InvalidArgumentException(
                sprintf('Argument "%s" is not a required argument in the signature', $name)
            );
        }

        if ($value === '') {
            throw new InvalidArgumentException(
                sprintf('Required argument "%s" cannot be empty', $name)
            );
        }

        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Sets a value for a default argument.
     *
     * @param  string  $name  The argument name
     * @param  string|null  $value  The value (null uses the default or '~')
     *
     * @throws InvalidArgumentException If the argument does not exist or is not a default argument
     */
    public function setDefault(string $name, ?string $value = null): self
    {
        if (! $this->structure->hasDefault($name)) {
            throw new InvalidArgumentException(
                sprintf('Argument "%s" is not a default argument in the signature', $name)
            );
        }

        if ($value === null) {
            $defaults = $this->structure->getDefaults();
            $this->arguments[$name] = $defaults[$name] ?? '~';
        } elseif ($value === '') {
            $this->arguments[$name] = '~';
        } else {
            $this->arguments[$name] = $value;
        }

        return $this;
    }

    /**
     * Sets a value for a variadic argument.
     *
     * @param  string  $name  The argument name
     * @param  string|array<string>  $value  The values (comma-separated string or array)
     *
     * @throws InvalidArgumentException If the argument does not exist or is not variadic
     */
    public function setVariadic(string $name, string|array $value): self
    {
        if (! $this->structure->hasVariadic($name)) {
            throw new InvalidArgumentException(
                sprintf('Argument "%s" is not a variadic argument in the signature', $name)
            );
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Sets a flag value.
     *
     * @param  string  $name  The flag name with '--' (e.g., '--verbose')
     * @param  bool  $active  Whether the flag is active
     *
     * @throws InvalidArgumentException If the flag does not exist in the signature
     */
    public function setFlag(string $name, bool $active = true): self
    {
        $flagName = ltrim($name, '--');

        if (! $this->structure->hasFlag($flagName)) {
            throw new InvalidArgumentException(
                sprintf('Flag "%s" does not exist in signature', $name)
            );
        }

        $this->flags[$name] = $active;

        return $this;
    }

    /**
     * Toggles a flag's state.
     *
     * @param  string  $name  The flag name with '--' (e.g., '--verbose')
     *
     * @throws InvalidArgumentException If the flag does not exist in the signature
     */
    public function toggleFlag(string $name): self
    {
        $flagName = ltrim($name, '--');

        if (! $this->structure->hasFlag($flagName)) {
            throw new InvalidArgumentException(
                sprintf('Flag "%s" does not exist in signature', $name)
            );
        }

        $this->flags[$name] = ! $this->flags[$name];

        return $this;
    }

    /**
     * Checks if a flag is active.
     *
     * @param  string  $name  The flag name with '--' (e.g., '--verbose')
     */
    public function hasFlag(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }

    /**
     * Sets a custom tag value.
     *
     * Custom tags are placed at the end of the query in the format <key="value">.
     *
     * @param  string  $key  The tag key
     * @param  string  $value  The tag value
     */
    public function setCustom(string $key, string $value): self
    {
        $this->customTags[$key] = $value;

        return $this;
    }

    /**
     * Sets multiple custom tags at once.
     *
     * @param  array<string, string>  $tags  Associative array of key => value
     */
    public function setCustoms(array $tags): self
    {
        foreach ($tags as $key => $value) {
            $this->customTags[$key] = $value;
        }

        return $this;
    }

    /**
     * Removes a custom tag.
     *
     * @param  string  $key  The tag key to remove
     */
    public function removeCustom(string $key): self
    {
        unset($this->customTags[$key]);

        return $this;
    }

    /**
     * Gets a custom tag value.
     *
     * @param  string  $key  The tag key
     * @return string|null The tag value or null if not found
     */
    public function getCustom(string $key): ?string
    {
        return $this->customTags[$key] ?? null;
    }

    /**
     * Gets all custom tags.
     *
     * @return array<string, string>
     */
    public function getCustoms(): array
    {
        return $this->customTags;
    }

    /**
     * Gets the current value of an argument.
     *
     * @param  string  $name  The argument name
     * @return string|null The value or null if not set
     */
    public function getArgument(string $name): ?string
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * Gets all arguments.
     *
     * @return array<string, string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Gets all flags.
     *
     * @return array<string, bool>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Resets all arguments to their default values.
     */
    public function reset(): self
    {
        $this->arguments = [];
        $this->flags = [];
        $this->customTags = [];

        foreach ($this->structure->getDefaults() as $name => $defaultValue) {
            $this->arguments[$name] = $defaultValue ?? '~';
        }

        foreach ($this->structure->getFlags() as $flag) {
            $this->flags['--'.$flag] = false;
        }

        $this->validated = false;

        return $this;
    }

    /**
     * Validates the current query against the signature.
     *
     * @return ValidationResultRecord The validation result
     */
    public function validate(): ValidationResultRecord
    {
        $query = $this->buildQueryString();
        $parser = new SignatureParser;

        $this->validationResult = $parser->validate($this->structure->getRaw(), $query);
        $this->validated = true;

        return $this->validationResult;
    }

    /**
     * Checks if the current query is valid.
     */
    public function isValid(): bool
    {
        if (! $this->validated) {
            $this->validate();
        }

        return $this->validationResult->isValid;
    }

    /**
     * Gets validation errors for the current query.
     */
    public function getErrors(): StringTypedCollection
    {
        if (! $this->validated) {
            $this->validate();
        }

        return $this->validationResult->errors;
    }

    /**
     * Builds the final query string.
     *
     * @return string The built query
     *
     * @throws InvalidArgumentException If the query is invalid
     */
    public function build(): string
    {
        $query = $this->buildQueryString();

        $parser = new SignatureParser;
        $result = $parser->validate($this->structure->getRaw(), $query);

        if (! $result->isValid) {
            $errors = $result->errors->join(', ');
            throw new InvalidArgumentException(
                sprintf('Invalid query: %s', $errors)
            );
        }

        return $query;
    }

    /**
     * Builds the query string without validation.
     */
    private function buildQueryString(): string
    {
        $parts = [$this->source];

        // Required arguments first (in order)
        foreach ($this->structure->getRequireds() as $name) {
            $value = $this->arguments[$name] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            } else {
                $parts[] = '~';
            }
        }

        // Default arguments next (in order)
        foreach (array_keys($this->structure->getDefaults()) as $name) {
            $value = $this->arguments[$name] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            } else {
                $parts[] = '~';
            }
        }

        // Variadic arguments
        foreach ($this->structure->getVariadics() as $name) {
            $value = $this->arguments[$name] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = '['.$value.']';
            }
        }

        // Add active flags
        foreach ($this->flags as $name => $active) {
            if ($active) {
                $parts[] = $name;
            }
        }

        // Add custom tags at the end
        foreach ($this->customTags as $key => $value) {
            $parts[] = '<'.$key.'="'.$value.'">';
        }

        return implode(' ', $parts);
    }

    /**
     * Gets the signature structure.
     */
    public function getStructure(): SignatureStructureVO
    {
        return $this->structure;
    }

    /**
     * Gets the source (command name).
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Checks if the builder has been built.
     */
    public function isBuilt(): bool
    {
        return false;
    }

    /**
     * Creates a clone of the builder.
     */
    public function __clone()
    {
        $this->arguments = $this->arguments;
        $this->flags = $this->flags;
        $this->customTags = $this->customTags;
        $this->validated = false;
    }
}
