<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
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
 *
 *
 * @example
 * $vo = new SignatureVO(
 *     'backup {source} {destination} {format=zip} {--force}',
 *     'backup /var/www /backup tar.gz --force'
 * );
 *
 * echo $vo->getSource();              // 'backup'
 * echo $vo->getRequired('source');    // '/var/www'
 * echo $vo->getDefault('format');     // 'tar.gz'
 * echo $vo->getFlag('force');         // true
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

    private StrictDataObject $parsed;

    /**
     * Constructs a new SignatureVO instance.
     *
     * @param  string  $signature  The command signature (e.g., 'backup {source} {--force}')
     * @param  string  $query  The actual command query (e.g., 'backup /var/www --force')
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

        $this->parsed = new StrictDataObject([
            'source' => $this->source,
            'required' => $this->required,
            'default' => $this->default,
            'variadic' => $this->variadic,
            'flags' => $this->flags,
        ]);
    }

    private string $source;
}
