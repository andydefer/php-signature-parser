<?php

// src/Parsers/OptionsParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;

final class OptionsParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
    {
        $options = [];
        $newSignature = [];
        $newQuery = [];
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

                $options[$name] = $found;
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
            'result' => ['options' => $options],
            'signature' => $newSignature,
            'query' => $newQuery,
        ];
    }
}
