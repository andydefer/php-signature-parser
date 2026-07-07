<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

interface ParserRegistryInterface
{
    public function addParser(ParserInterface $parser): self;

    public function removeParser(string $parserClass): self;

    /** @return array<ParserInterface> */
    public function getParsers(): array;
}
