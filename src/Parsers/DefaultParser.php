<?php

// src/Parsers/DefaultParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers;

use AndyDefer\SignatureParser\Contracts\ParserInterface;

final class DefaultParser implements ParserInterface
{
    public function parse(array $signature, array $query): array
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

                // Chercher une valeur dans la query
                for ($i = $queryIndex; $i < $queryCount; $i++) {
                    $current = $query[$i];
                    // Si on tombe sur un crochet (variadique) ou -- (option), on s'arrête
                    if (str_starts_with($current, '[') || str_starts_with($current, '--')) {
                        break;
                    }
                    // Si c'est une valeur simple, on la prend
                    if (! empty($current) && ! str_starts_with($current, '[') && ! str_starts_with($current, '--')) {
                        $value = $current;
                        $found = true;
                        $queryIndex = $i + 1;
                        break;
                    }
                }

                // Si aucune valeur trouvée, on garde la valeur par défaut
                if (! $found) {
                    // Ne pas incrémenter queryIndex si aucune valeur n'a été consommée
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

        // Ajouter le reste de la query
        if ($queryIndex < $queryCount) {
            for ($i = $queryIndex; $i < $queryCount; $i++) {
                $newQuery[] = $query[$i];
            }
        }

        return [
            'result' => ['default' => $default],
            'signature' => $newSignature,
            'query' => $newQuery,
        ];
    }
}
