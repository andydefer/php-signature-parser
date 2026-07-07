<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\SignatureParser\Records\OptionRecord;

/**
 * Collection of OptionRecord.
 *
 * @extends TypedCollection<OptionRecord>
 */
final class OptionCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(OptionRecord::class);
    }

    /**
     * Récupère la valeur d'une option par son nom.
     */
    public function get(string $name): bool
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->value;
            }
        }

        return false;
    }

    /**
     * Vérifie si une option existe par son nom.
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
     * Vérifie si une option est active (valeur = true).
     */
    public function isActive(string $name): bool
    {
        return $this->get($name) === true;
    }

    /**
     * Récupère tous les noms des options.
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
     * Récupère toutes les options actives (valeur = true).
     *
     * @return array<string>
     */
    public function getActiveNames(): array
    {
        $active = [];
        foreach ($this->items as $item) {
            if ($item->value === true) {
                $active[] = $item->name;
            }
        }

        return $active;
    }

    /**
     * Convertit la collection en tableau associatif [nom => booléen].
     *
     * @return array<string, bool>
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
