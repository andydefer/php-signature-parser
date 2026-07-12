<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\EnumCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;

/**
 * Record representing the parsed result of a command signature and query.
 *
 * Contains both standard components (source, requireds, defaults, variadics, flags, enums)
 * and custom data extracted by custom parsers.
 *
 * @example
 * $record = new ParsedSignatureRecord(
 *     source: 'backup',
 *     requireds: new ArgumentCollection(),
 *     defaults: new ArgumentCollection(),
 *     variadics: new VariadicArgumentCollection(),
 *     flags: new FlagCollection(),
 *     enums: new EnumCollection(),
 *     custom_data: new StrictDataObject(['files' => ['/var/www/file.txt']])
 * );
 */
final class ParsedSignatureRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $source,
        public readonly ArgumentCollection $requireds,
        public readonly ArgumentCollection $defaults,
        public readonly VariadicArgumentCollection $variadics,
        public readonly FlagCollection $flags,
        public readonly EnumCollection $enums = new EnumCollection,
        public readonly StrictDataObject $custom_data = new StrictDataObject,
    ) {}
}
