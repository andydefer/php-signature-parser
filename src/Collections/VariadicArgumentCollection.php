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
}
