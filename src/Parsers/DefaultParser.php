<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;

final class DefaultParser implements ParserInterface
{
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $default = [];
        $newSignature = [];
        $newQuery = [];
        $queryIndex = 0;
        $queryCount = count($query);

        foreach ($signature as $element) {
            if (str_contains($element, '=')) {
                [$name, $defaultValue] = explode('=', $element, 2);

                $value = $defaultValue;
                $found = false;

                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];
                    if (str_starts_with($current, '[') || str_starts_with($current, '--')) {
                        break;
                    }
                    if (! empty($current) && ! str_starts_with($current, '[') && ! str_starts_with($current, '--')) {
                        $value = $current;
                        $found = true;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                if (! $found) {
                    // Ne pas incrémenter queryIndex
                }

                $default[$name] = $value;
            } else {
                $newSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $newQuery[] = $query[$queryIndex];
                    $queryIndex++;
                } else {
                    $newQuery[] = '';
                }
            }
        }

        if ($queryIndex < $queryCount) {
            for ($i = $queryIndex; $i < $queryCount; $i++) {
                $newQuery[] = $query[$i];
            }
        }

        return ParsedResultRecord::from([
            'data' => ['default' => $default],
            'signature' => $newSignature,
            'query' => $newQuery,
        ]);
    }
}
