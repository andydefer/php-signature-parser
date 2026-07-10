<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;

/**
 * Record representing the parsed result of a command signature and query.
 *
 * Contains both standard components (source, required, default, variadic, flags)
 * and custom data extracted by custom parsers.
 *
 * @example
 * $record = new ParsedSignatureRecord(
 *     source: 'backup',
 *     required: new ArgumentCollection(),
 *     default: new ArgumentCollection(),
 *     variadic: new VariadicArgumentCollection(),
 *     flags: new FlagCollection(),
 *     data: new StrictDataObject(['files' => ['/var/www/file.txt']])
 * );
 */
final class ParsedSignatureRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $source,
        public readonly ArgumentCollection $required,
        public readonly ArgumentCollection $default,
        public readonly VariadicArgumentCollection $variadic,
        public readonly FlagCollection $flags,
        public readonly StrictDataObject $custom_data = new StrictDataObject,
    ) {}
}
