<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;

/**
 * Collection of VariadicArgumentRecord.
 *
 * @extends TypedCollection<VariadicArgumentRecord>
 */
final class VariadicArgumentCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(VariadicArgumentRecord::class);
    }

    /**
     * Récupère les valeurs d'un argument variadique par son nom.
     *
     * @return array<string>
     */
    public function get(string $name): array
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item->values->toArray();
            }
        }

        return [];
    }

    /**
     * Vérifie si un argument variadique existe par son nom.
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
     * Récupère tous les noms des arguments variadiques.
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
     * Récupère toutes les valeurs de tous les arguments variadiques.
     *
     * @return array<string>
     */
    public function getAllValues(): array
    {
        $allValues = [];
        foreach ($this->items as $item) {
            $allValues = array_merge($allValues, $item->values->toArray());
        }

        return $allValues;
    }

    /**
     * Convertit la collection en tableau associatif [nom => array].
     *
     * @return array<string, array<string>>
     */
    public function toAssociativeArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item->name] = $item->values->toArray();
        }

        return $result;
    }

    /**
     * Compte le nombre total de valeurs dans tous les arguments variadiques.
     */
    public function countAllValues(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->values->count();
        }

        return $count;
    }
}
