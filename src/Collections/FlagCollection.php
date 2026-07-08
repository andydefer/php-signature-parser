<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\SignatureParser\Records\FlagRecord;

/**
 * Collection of FlagRecord.
 *
 * @extends TypedCollection<FlagRecord>
 */
final class FlagCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(FlagRecord::class);
    }

    /**
     * Récupère la valeur d'un flag par son nom.
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
     * Vérifie si un flag existe par son nom.
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
     * Vérifie si un flag est actif (valeur = true).
     */
    public function isActive(string $name): bool
    {
        return $this->get($name) === true;
    }

    /**
     * Récupère tous les noms des flags.
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
     * Récupère tous les flags actifs (valeur = true).
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
