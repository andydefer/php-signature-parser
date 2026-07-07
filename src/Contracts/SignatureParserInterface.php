<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;

interface SignatureParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord;

    public function extractSignatureElements(string $signature): StringTypedCollection;

    public function extractQueryElements(string $query): StringTypedCollection;
}
