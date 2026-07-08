<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

final class SourceParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $source = $signature[0] ?? '';

        array_shift($signature);
        array_shift($query);

        return ParsedResultRecord::from([
            'data' => ['source' => $source],
            'signature' => $signature,
            'query' => $query,
        ]);
    }

    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        if (empty($signature)) {
            $errors->add('Missing source (command name)');
            $suggestions->add('Add a command name as the first argument');
        }

        if (empty($query)) {
            $errors->add('Missing query');
            $suggestions->add('Provide a query to execute');
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }
}
