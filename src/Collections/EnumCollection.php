<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\SignatureParser\Enums\ValueState;
use AndyDefer\SignatureParser\Records\EnumRecord;

/**
 * Collection of EnumRecord.
 *
 * @extends TypedCollection<EnumRecord>
 */
final class EnumCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(EnumRecord::class);
    }

    /**
     * Get the value of an enum by its name.
     */
    public function get(string $name): mixed
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * Check if an enum exists by its name.
     */
    public function has(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all enum names.
     *
     * @return array<string>
     */
    public function getNames(): array
    {
        $names = [];
        foreach ($this->items as $item) {
            $names[] = $item->name;
        }

        return $names;
    }

    /**
     * Get all enum values.
     *
     * @return array<mixed>
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->items as $item) {
            $values[] = $item->value;
        }

        return $values;
    }

    /**
     * Get all allowed values for an enum by its name.
     *
     * @return array<string>|null
     */
    public function getAllowedValues(string $name): ?array
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->allowed_values->toArray();
            }
        }

        return null;
    }

    /**
     * Check if a value is allowed for an enum.
     */
    public function isAllowed(string $name, string $value): bool
    {
        $allowed = $this->getAllowedValues($name);

        if ($allowed === null) {
            return false;
        }

        return in_array($value, $allowed, true);
    }

    /**
     * Check if an enum is required.
     */
    public function isRequired(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value_state === ValueState::REQUIRED;
            }
        }

        return false;
    }

    /**
     * Check if an enum is optional (nullable).
     */
    public function isOptional(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value_state === ValueState::OPTIONAL;
            }
        }

        return false;
    }

    /**
     * Check if an enum has a default value.
     */
    public function hasDefault(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value_state === ValueState::DEFAULTED;
            }
        }

        return false;
    }

    /**
     * Get the default value of an enum.
     */
    public function getDefault(string $name): mixed
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->default_value;
            }
        }

        return null;
    }

    /**
     * Convert the collection to an associative array [name => value].
     *
     * @return array<string, mixed>
     */
    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item->name] = $item->value;
        }

        return $result;
    }

    /**
     * Convert the collection to an array with full enum data.
     *
     * @return array<array<string, mixed>>
     */
    public function toFullArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = [
                'name' => $item->name,
                'value' => $item->value,
                'allowed_values' => $item->allowed_values->toArray(),
                'default_value' => $item->default_value,
                'value_state' => $item->value_state->name,
            ];
        }

        return $result;
    }
}
