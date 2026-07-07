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
}
