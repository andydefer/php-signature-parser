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
}
