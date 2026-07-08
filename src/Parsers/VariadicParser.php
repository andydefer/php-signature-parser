<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

/**
 * Parses variadic arguments from a command signature.
 *
 * Variadic arguments are defined with `*` suffix and capture multiple values
 * from the query.
 */
final class VariadicParser implements ParserInterface
{
    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $variadic = [];
        $remainingSignature = [];
        $remainingQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_contains($element, '*')) {
                $name = str_replace('*', '', $element);
                $values = [];

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];

                    if (str_starts_with($current, '--')) {
                        break;
                    }

                    if (str_starts_with($current, '[') && str_ends_with($current, ']')) {
                        $content = trim($current, '[]');

                        if (! empty($content)) {
                            $parts = array_map('trim', explode(',', $content));

                            foreach ($parts as $part) {
                                if (! empty($part)) {
                                    $values[] = $part;
                                }
                            }
                        }

                        $queryIndex = $i + 1;
                        break;
                    }
                }

                $variadic[$name] = $values;
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
            'data' => ['variadic' => $variadic],
            'signature' => $remainingSignature,
            'query' => $remainingQuery,
        ]);
    }
}
