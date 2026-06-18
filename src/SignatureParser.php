<?php

// src/SignatureParser.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\OptionsParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;

final class SignatureParser
{
    private array $parsers = [];

    public function __construct()
    {
        // Ajouter tous les parseurs par défaut dans l'ordre
        $this->addParser(new SourceParser);
        $this->addParser(new RequiredParser);
        $this->addParser(new DefaultParser);
        $this->addParser(new VariadicParser);
        $this->addParser(new OptionsParser);
    }

    public function addParser(ParserInterface $parser): self
    {
        $this->parsers[] = $parser;

        return $this;
    }

    public function removeParser(string $parserClass): self
    {
        $this->parsers = array_filter(
            $this->parsers,
            fn ($parser) => get_class($parser) !== $parserClass
        );

        return $this;
    }

    public function getParsers(): array
    {
        return $this->parsers;
    }

    public function parse(string $signature, string $query): array
    {
        $signatureElements = $this->extractSignatureElements($signature);
        $queryElements = $this->extractQueryElements($query);

        $result = [];

        foreach ($this->parsers as $parser) {
            $parsed = $parser->parse($signatureElements, $queryElements);
            $result = array_merge($result, $parsed['result']);
            $signatureElements = $parsed['signature'];
            $queryElements = $parsed['query'];
        }

        return $result;
    }

    public function extractSignatureElements(string $signature): array
    {
        preg_match_all('/\{([^}]+)\}|(\S+)/', $signature, $matches);
        $result = [];

        foreach ($matches[0] as $index => $match) {
            if (isset($matches[1][$index]) && $matches[1][$index] !== '') {
                $result[] = $matches[1][$index];
            } else {
                $result[] = $match;
            }
        }

        return $result;
    }

    public function extractQueryElements(string $query): array
    {
        $parts = explode(' ', $query);
        $result = [];
        $inVariadic = false;
        $variadicBuffer = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                if ($inVariadic && ! empty($variadicBuffer)) {
                    $result[] = '['.implode(' ', $variadicBuffer).']';
                    $variadicBuffer = [];
                    $inVariadic = false;
                }
                $result[] = $part;

                continue;
            }

            if (str_starts_with($part, '[')) {
                $inVariadic = true;
                $part = ltrim($part, '[');
            }

            if (str_ends_with($part, ']')) {
                $part = rtrim($part, ']');
                $variadicBuffer[] = $part;
                $result[] = '['.implode(' ', $variadicBuffer).']';
                $variadicBuffer = [];
                $inVariadic = false;

                continue;
            }

            if ($inVariadic) {
                $variadicBuffer[] = $part;
            } else {
                $result[] = $part;
            }
        }

        if (! empty($variadicBuffer)) {
            $result[] = '['.implode(' ', $variadicBuffer).']';
        }

        return $result;
    }
}
