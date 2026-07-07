<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\SignatureParser\Records\ArgumentRecord;

/**
 * Collection of ArgumentRecord.
 *
 * @extends TypedCollection<ArgumentRecord>
 */
final class ArgumentCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ArgumentRecord::class);
    }

    /**
     * Récupère la valeur d'un argument par son nom.
     */
    public function get(string $name): ?string
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value;
            }
        }

        return null;
    }

    /**
     * Vérifie si un argument existe par son nom.
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
     * Récupère tous les noms des arguments.
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
     * Récupère toutes les valeurs des arguments.
     *
     * @return array<string>
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
     * Convertit la collection en tableau associatif [nom => valeur].
     *
     * @return array<string, string>
     */
    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item->name] = $item->value;
        }

        return $result;
    }
}
