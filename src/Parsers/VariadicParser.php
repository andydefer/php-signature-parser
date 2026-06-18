<?php

// src/Parsers/VariadicParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;

final class VariadicParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
    {
        $variadic = [];
        $newSignature = [];
        $newQuery = [];
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
                $newSignature[] = $element;
                if ($queryIndex < $queryCount) {
                    $newQuery[] = $query[$queryIndex];
                    $queryIndex++;
                }
            }
        }

        if ($queryIndex < $queryCount) {
            for ($i = $queryIndex; $i < $queryCount; $i++) {
                $newQuery[] = $query[$i];
            }
        }

        return [
            'result' => ['variadic' => $variadic],
            'signature' => $newSignature,
            'query' => $newQuery,
        ];
    }
}
