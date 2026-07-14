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
 * - Enum arguments (::name->[values])
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
    private array $custom_tags = [];

    /** @var array<string, string> */
    private array $enums = [];

    private string $source;

    private bool $validated = false;

    private ValidationResultRecord $validation_result;

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

        // Initialiser avec les valeurs par défaut - ignorer les tokens d'énum
        foreach ($structure->getDefaults() as $name => $defaultValue) {
            // Si le nom contient '->[' c'est un token d'énum, on le saute
            if (str_contains($name, '->[')) {
                continue;
            }
            $this->arguments[$name] = $defaultValue ?? '_';
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
        $queryTokens = explode(' ', $query);

        foreach ($parsed->requireds as $arg) {
            $this->arguments[$arg->name] = $arg->value !== '' ? $arg->value : '_';
        }

        foreach ($parsed->defaults as $arg) {
            // Vérifier si c'est un enum ou un token d'énum
            if ($this->isEnumArgument($arg->name) || str_contains($arg->name, '->[')) {
                continue;
            }
            $this->arguments[$arg->name] = $arg->value !== '' ? $arg->value : '_';
        }

        foreach ($parsed->variadics as $arg) {
            $values = $arg->values->join(' ');
            $this->arguments[$arg->name] = $values !== '' ? $values : '_';
        }

        foreach ($parsed->flags as $flag) {
            $this->flags['--'.$flag->name] = $flag->value;
        }

        // Extract enums
        foreach ($parsed->enums as $enum) {
            if ($enum->value !== null && in_array((string) $enum->value, $queryTokens, true)) {
                $this->enums[$enum->name] = $enum->value;
            } elseif ($enum->value === '_' && in_array('_', $queryTokens, true)) {
                $this->enums[$enum->name] = '_';
            }
        }

        // Extract custom tags
        $customData = $parsed->custom_data->toArray();
        foreach ($customData as $key => $value) {
            $this->custom_tags[$key] = $value;
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
            if ($value === null || $value === '') {
                throw new InvalidArgumentException(
                    sprintf('Required argument "%s" cannot be null or empty', $name)
                );
            }
            $this->arguments[$name] = $value;
        } elseif ($this->structure->hasDefault($name)) {
            if ($value === null) {
                $defaults = $this->structure->getDefaults();
                $this->arguments[$name] = $defaults[$name] ?? '_';
            } elseif ($value === '') {
                $this->arguments[$name] = '_';
            } else {
                $this->arguments[$name] = $value;
            }
        } elseif ($this->structure->hasVariadic($name)) {
            if ($value === null || $value === '') {
                $this->arguments[$name] = '_';
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
     * @param  string|null  $value  The value (null uses the default or '_')
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
            $this->arguments[$name] = $defaults[$name] ?? '_';
        } elseif ($value === '') {
            $this->arguments[$name] = '_';
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
     * Sets a value for an enum argument.
     *
     * @param  string  $name  The enum name
     * @param  string|null  $value  The value (null sets to default or '_')
     *
     * @throws InvalidArgumentException If the enum does not exist in the signature
     */
    public function setEnum(string $name, ?string $value = null): self
    {
        // Vérifier si l'énum existe dans la signature
        $enumFound = false;
        $allowedValues = [];
        $defaultValue = null;
        $isRequired = false;
        $isOptional = false;

        $enumToken = '::'.$name.'->';
        foreach (explode(' ', $this->structure->getRaw()) as $token) {
            if (str_starts_with($token, $enumToken)) {
                $enumFound = true;
                preg_match('/::'.$name.'->\[([^\]]+)\](?:=([^ ]+))?/', $token, $matches);
                if (isset($matches[1])) {
                    $allowedValues = array_map('trim', explode(',', $matches[1]));
                    $allowedValues = array_filter($allowedValues, fn ($v) => $v !== '');
                }
                if (isset($matches[2])) {
                    $defaultPart = $matches[2];
                    if ($defaultPart === '*') {
                        $isRequired = true;
                    } elseif ($defaultPart === '?') {
                        $isOptional = true;
                    } else {
                        $defaultValue = $defaultPart;
                    }
                } else {
                    $isRequired = true;
                }
                break;
            }
        }

        if (! $enumFound) {
            throw new InvalidArgumentException(
                sprintf('Enum "%s" does not exist in signature', $name)
            );
        }

        // Valider la valeur
        if ($value !== null && ! empty($allowedValues) && ! in_array($value, $allowedValues, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid value "%s" for enum "%s". Allowed: %s',
                    $value,
                    $name,
                    implode(', ', $allowedValues)
                )
            );
        }

        // Gérer le cas null
        if ($value === null) {
            if ($isRequired) {
                throw new InvalidArgumentException(
                    sprintf('Required enum "%s" cannot be null', $name)
                );
            }
            if ($isOptional) {
                $this->enums[$name] = '_';
            } else {
                $this->enums[$name] = $defaultValue ?? '_';
            }
        } else {
            $this->enums[$name] = $value;
        }

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
        $this->custom_tags[$key] = $value;

        return $this;
    }

    /**
     * Sets multiple custom tags at once.
     *
     * @param  array<string, string>  $tags  StrictAssociative array of key => value
     */
    public function setCustoms(array $tags): self
    {
        foreach ($tags as $key => $value) {
            $this->custom_tags[$key] = $value;
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
        unset($this->custom_tags[$key]);

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
        return $this->custom_tags[$key] ?? null;
    }

    /**
     * Gets all custom tags.
     *
     * @return array<string, string>
     */
    public function getCustoms(): array
    {
        return $this->custom_tags;
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
     * Gets an enum value.
     *
     * @param  string  $name  The enum name
     * @return string|null The value or null if not set
     */
    public function getEnum(string $name): ?string
    {
        return $this->enums[$name] ?? null;
    }

    /**
     * Gets all enums.
     *
     * @return array<string, string>
     */
    public function getEnums(): array
    {
        return $this->enums;
    }

    /**
     * Resets all arguments to their default values.
     */
    public function reset(): self
    {
        $this->arguments = [];
        $this->flags = [];
        $this->custom_tags = [];
        $this->enums = [];

        foreach ($this->structure->getDefaults() as $name => $defaultValue) {
            if (str_contains($name, '->[')) {
                continue;
            }
            $this->arguments[$name] = $defaultValue ?? '_';
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

        $this->validation_result = $parser->validate($this->structure->getRaw(), $query);
        $this->validated = true;

        return $this->validation_result;
    }

    /**
     * Checks if the current query is valid.
     */
    public function isValid(): bool
    {
        if (! $this->validated) {
            $this->validate();
        }

        return $this->validation_result->isValid;
    }

    /**
     * Gets validation errors for the current query.
     */
    public function getErrors(): StringTypedCollection
    {
        if (! $this->validated) {
            $this->validate();
        }

        return $this->validation_result->errors;
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

        // 1. Enums d'abord (après la source, avant tout)
        foreach ($this->enums as $name => $value) {
            $parts[] = $value;
        }

        // 2. Default enums (si l'énum n'est pas défini dans $this->enums)
        foreach ($this->structure->getEnums() as $name => $enumData) {
            // Si l'énum n'est pas défini dans $this->enums
            if (! isset($this->enums[$name])) {
                // Si l'énum a une valeur par défaut (ni required ni optional)
                if (! $enumData['is_required'] && ! $enumData['is_optional'] && $enumData['default_value'] !== null && $enumData['default_value'] !== '') {
                    $parts[] = $enumData['default_value'];
                }
                // Si optional, on ne fait rien
                // Si required, on ne fait rien (l'utilisateur doit le fournir)
            }
        }

        // 3. Required arguments (après les enums)
        foreach ($this->structure->getRequireds() as $name) {
            if ($this->isEnumArgument($name)) {
                continue;
            }
            $value = $this->arguments[$name] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            } else {
                $parts[] = '_';
            }
        }

        // 4. Default arguments (non enum)
        foreach (array_keys($this->structure->getDefaults()) as $name) {
            if ($this->isEnumArgument($name)) {
                continue;
            }
            $value = $this->arguments[$name] ?? null;
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            } else {
                $parts[] = '_';
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
        foreach ($this->custom_tags as $key => $value) {
            $parts[] = '<'.$key.'="'.$value.'">';
        }

        return implode(' ', $parts);
    }

    /**
     * Check if an argument is an enum.
     */
    private function isEnumArgument(string $name): bool
    {
        return str_contains($this->structure->getRaw(), '::'.$name.'->');
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
        $this->custom_tags = $this->custom_tags;
        $this->enums = $this->enums;
        $this->validated = false;
    }
}
