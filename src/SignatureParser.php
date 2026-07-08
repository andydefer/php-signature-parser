<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\Formatters\TextFormatter;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\FlagParser;
use AndyDefer\SignatureParser\Parsers\NullableParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;
use AndyDefer\SignatureParser\Records\ArgumentRecord;
use AndyDefer\SignatureParser\Records\FlagRecord;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;

/**
 * Main parser for CLI command signatures and queries.
 *
 * Uses a chain of responsibility pattern with specialized parsers to extract:
 * - Source (command name)
 * - Required arguments: {name}
 * - Default arguments: {name=value}
 * - Nullable arguments: {name?}
 * - Variadic arguments: {name*}
 * - Boolean flags: {--flag}
 */
final class SignatureParser implements ParserRegistryInterface, SignatureParserInterface
{
    /** @var array<ParserInterface> */
    private array $parsers = [];

    /**
     * Initializes the parser with the default chain of responsibility.
     */
    public function __construct()
    {
        $this->addParser(new SourceParser);
        $this->addParser(new RequiredParser);
        $this->addParser(new NullableParser);
        $this->addParser(new DefaultParser);
        $this->addParser(new VariadicParser);
        $this->addParser(new FlagParser);
    }

    /**
     * {@inheritDoc}
     */
    public function addParser(ParserInterface $parser): self
    {
        $this->parsers[] = $parser;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function removeParser(string $parserClass): self
    {
        $this->parsers = array_filter(
            $this->parsers,
            fn ($parser) => get_class($parser) !== $parserClass
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getParsers(): array
    {
        return $this->parsers;
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function validate(string $signature, string $query): ValidationResultRecord
    {
        $signatureElements = $this->extractSignatureElements($signature);
        $queryElements = $this->extractQueryElements($query);

        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $currentSignature = $signatureElements;
        $currentQuery = $queryElements;

        foreach ($this->parsers as $parser) {
            $result = $parser->validate(
                $currentSignature->toArray(),
                $currentQuery->toArray()
            );

            foreach ($result->errors as $error) {
                $errors->add($error);
            }
            foreach ($result->suggestions as $suggestion) {
                $suggestions->add($suggestion);
            }

            $currentSignature = $result->signature ?? $currentSignature;
            $currentQuery = $result->query ?? $currentQuery;
        }

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(string $signature, string $query): bool
    {
        return $this->validate($signature, $query)->isValid;
    }

    /**
     * {@inheritDoc}
     */
    public function getValidationErrors(string $signature, string $query): StringTypedCollection
    {
        return $this->validate($signature, $query)->errors;
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
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

    /**
     * Builds a ParsedSignatureRecord from the parsed data.
     *
     * @param  array<string, mixed>  $data  The parsed data from all parsers
     * @return ParsedSignatureRecord The structured result
     */
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

        $nullable = new ArgumentCollection;
        foreach ($data['nullable'] ?? [] as $name => $value) {
            $nullable->add(new ArgumentRecord($name, $value));
        }

        $variadic = new VariadicArgumentCollection;
        foreach ($data['variadic'] ?? [] as $name => $values) {
            $variadic->add(new VariadicArgumentRecord(
                $name,
                StringTypedCollection::from($values)
            ));
        }

        $flags = new FlagCollection;
        foreach ($data['flags'] ?? [] as $name => $value) {
            $flags->add(new FlagRecord($name, $value));
        }

        $rawData = [
            'source' => $data['source'] ?? '',
            'required' => $required,
            'default' => $default,
            'nullable' => $nullable,
            'variadic' => $variadic,
            'flags' => $flags,
        ];

        $normalizedData = TextFormatter::format(NormalizerChain::get()->normalize($rawData));

        return ParsedSignatureRecord::from($normalizedData);
    }
}
