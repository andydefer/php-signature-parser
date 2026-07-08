<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Fixtures;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class CustomParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        return ParsedResultRecord::from([
            'data' => ['custom' => 'value'],
            'signature' => $signature,
            'query' => $query,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        return new ValidationResultRecord(
            isValid: true,
            errors: new StringTypedCollection,
            suggestions: new StringTypedCollection
        );
    }
}
