<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\OptionCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\OptionsParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;
use AndyDefer\SignatureParser\Records\ArgumentRecord;
use AndyDefer\SignatureParser\Records\OptionRecord;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;

final class SignatureParser implements ParserRegistryInterface, SignatureParserInterface
{
    /** @var array<ParserInterface> */
    private array $parsers = [];

    public function __construct()
    {
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

    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        $signatureElements = $this->extractSignatureElements($signature);
        $queryElements = $this->extractQueryElements($query);

        $result = [];
        $currentSignature = $signatureElements;
        $currentQuery = $queryElements;

        foreach ($this->parsers as $parser) {
            $parsed = $parser->parse(
                $currentSignature->toArray(),
                $currentQuery->toArray()
            );

            $result = array_merge($result, $parsed->data->toArray());
            $currentSignature = $parsed->signature;
            $currentQuery = $parsed->query;
        }

        return $this->buildRecord($result);
    }

    private function buildRecord(array $data): ParsedSignatureRecord
    {
        $required = new ArgumentCollection;
        foreach ($data['required'] ?? [] as $name => $value) {
            $required->add(new ArgumentRecord($name, $value));
        }

        $default = new ArgumentCollection;
        foreach ($data['default'] ?? [] as $name => $value) {
            $default->add(new ArgumentRecord($name, $value));
        }

        $variadic = new VariadicArgumentCollection;
        foreach ($data['variadic'] ?? [] as $name => $values) {
            $variadic->add(new VariadicArgumentRecord(
                $name,
                StringTypedCollection::from($values)
            ));
        }

        $options = new OptionCollection;
        foreach ($data['options'] ?? [] as $name => $value) {
            $options->add(new OptionRecord($name, $value));
        }

        return new ParsedSignatureRecord(
            source: $data['source'] ?? '',
            required: $required,
            default: $default,
            variadic: $variadic,
            options: $options
        );
    }

    public function extractSignatureElements(string $signature): StringTypedCollection
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

        return StringTypedCollection::from($result);
    }

    public function extractQueryElements(string $query): StringTypedCollection
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

        return StringTypedCollection::from($result);
    }
}
