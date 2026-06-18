<?php

// src/Contracts/ParserInterface.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

interface ParserInterface
{
    /**
     * Parse la signature et la requête.
     *
     * @param  array<int, string>  $signature  Elements de la signature
     * @param  array<int, string>  $query  Elements de la requête
     * @return array{
     *     result: array<string, mixed>,
     *     signature: array<int, string>,
     *     query: array<int, string>
     * }
     */
    public function parse(array $signature, array $query): array;
}
