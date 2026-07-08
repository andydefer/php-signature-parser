<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Parses boolean flags from a command signature.
 *
 * Flags are defined with `--` prefix and are boolean values.
 */
final class FlagParser implements ParserInterface
{
    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $flags = [];
        $remainingSignature = [];
        $remainingQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_starts_with($element, '--')) {
                $name = ltrim($element, '--');
                $found = false;

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    if ($query[$i] === $element) {
                        $found = true;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                $flags[$name] = $found;
            } else {
                $remainingSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $remainingQuery[] = $query[$queryIndex];
                    $queryIndex++;
                }
            }
        }

        while ($queryIndex < $queryCount) {
            $remainingQuery[] = $query[$queryIndex];
            $queryIndex++;
        }

        return ParsedResultRecord::from([
            'data' => ['flags' => $flags],
            'signature' => $remainingSignature,
            'query' => $remainingQuery,
        ]);
    }
}
