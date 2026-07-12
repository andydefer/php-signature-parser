<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Collections\ArgumentCollection;
use AndyDefer\SignatureParser\Collections\EnumCollection;
use AndyDefer\SignatureParser\Collections\FlagCollection;
use AndyDefer\SignatureParser\Collections\VariadicArgumentCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Contracts\ParserRegistryInterface;
use AndyDefer\SignatureParser\Contracts\SignatureParserInterface;
use AndyDefer\SignatureParser\Enums\ValueState;
use AndyDefer\SignatureParser\Formatters\TextFormatter;
use AndyDefer\SignatureParser\Parsers\Customs\CustomTagParser;
use AndyDefer\SignatureParser\Parsers\DefaultParser;
use AndyDefer\SignatureParser\Parsers\EnumParser;
use AndyDefer\SignatureParser\Parsers\FlagParser;
use AndyDefer\SignatureParser\Parsers\RequiredParser;
use AndyDefer\SignatureParser\Parsers\SourceParser;
use AndyDefer\SignatureParser\Parsers\VariadicParser;
use AndyDefer\SignatureParser\Records\ArgumentRecord;
use AndyDefer\SignatureParser\Records\EnumRecord;
use AndyDefer\SignatureParser\Records\FlagRecord;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\Records\VariadicArgumentRecord;
use InvalidArgumentException;

/**
 * Parses CLI command signatures and queries into structured components.
 *
 * Uses a chain of responsibility pattern with specialized parsers to extract:
 * - Source (command name)
 * - Required arguments: {name}
 * - Default arguments: {name=value}
 * - Optional arguments: {name=?}
 * - Variadic arguments: {name*}
 * - Boolean flags: {--flag}
 * - Enum arguments: ::name->[value1,value2,value3]=default
 * - Custom tags: <key="value">
 *
 * @example
 * $parser = new SignatureParser();
 * $result = $parser->parse('greet {name} {--formal}', 'greet John --formal');
 * echo $result->source; // 'greet'
 * echo $result->required->first()->value; // 'John'
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
        $this->addParser(new EnumParser);      // ✅ Premier parser
        $this->addParser(new SourceParser);
        $this->addParser(new RequiredParser);
        $this->addParser(new DefaultParser);
        $this->addParser(new VariadicParser);
        $this->addParser(new FlagParser);
        $this->addParser(new CustomTagParser);
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
            fn ($parser): bool => $parser::class !== $parserClass
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
     *
     * @throws InvalidArgumentException If the signature order is invalid
     */
    public function parse(string $signature, string $query): ParsedSignatureRecord
    {
        $signatureElements = $this->extractSignatureElements($signature);
        $queryElements = $this->extractQueryElements($query);

        $orderErrors = $this->validateSignatureOrder($signatureElements);
        if ($orderErrors->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Invalid signature order: '.$orderErrors->join(', ')
            );
        }

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

        return $this->buildRecord(TextFormatter::format($result));
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

        $orderErrors = $this->validateSignatureOrder($signatureElements);
        foreach ($orderErrors as $error) {
            $errors->add($error);
        }

        if ($errors->isNotEmpty()) {
            return new ValidationResultRecord(
                isValid: false,
                errors: $errors,
                suggestions: $suggestions
            );
        }

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
    public function validateSignature(string $signature): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $elements = $this->extractSignatureElements($signature);

        if ($elements->isEmpty()) {
            $errors->add('Signature cannot be empty');

            return new ValidationResultRecord(
                isValid: false,
                errors: $errors,
                suggestions: $suggestions
            );
        }

        $orderErrors = $this->validateSignatureOrder($elements);
        foreach ($orderErrors as $error) {
            $errors->add($error);
        }

        $this->validateTokenSyntax($elements, $errors, $suggestions);
        $this->validateDuplicateArguments($elements, $errors, $suggestions);

        return new ValidationResultRecord(
            isValid: $errors->isEmpty(),
            errors: $errors,
            suggestions: $suggestions
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isSignatureValid(string $signature): bool
    {
        return $this->validateSignature($signature)->isValid;
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
                if ($inVariadic && $variadicBuffer !== []) {
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

        if ($variadicBuffer !== []) {
            $result[] = '['.implode(' ', $variadicBuffer).']';
        }

        return StringTypedCollection::from($result);
    }

    /**
     * Validates the order of arguments in the signature.
     *
     * Expected order:
     * 1. Source (command name)
     * 2. Required arguments: {name}
     * 3. Default arguments: {name=value}
     * 4. Optional arguments: {name=?}
     * 5. Variadic arguments: {name*}
     * 6. Flags: {--flag}
     *
     * @return StringTypedCollection The order errors
     */
    private function validateSignatureOrder(StringTypedCollection $signatureElements): StringTypedCollection
    {
        $errors = new StringTypedCollection;
        $elements = $signatureElements->toArray();

        if ($elements === []) {
            return $errors;
        }

        $lastType = 'source';
        $foundFlags = false;

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue;
            }

            $type = $this->determineElementType($element);

            if ($type === 'flags') {
                $foundFlags = true;

                continue;
            }

            if ($foundFlags) {
                $errors->add("Argument '{$element}' cannot appear after flags");

                continue;
            }

            if ($type === 'required' && $lastType !== 'source' && $lastType !== 'required') {
                $errors->add("Required argument '{$element}' must appear before default, optional, variadic or flags");
            }

            if ($type === 'default' && $lastType !== 'source' && $lastType !== 'required' && $lastType !== 'default') {
                $errors->add("Default argument '{$element}' must appear after required arguments and before optional, variadic or flags");
            }

            if ($type === 'variadic' && $lastType !== 'source' && $lastType !== 'required' && $lastType !== 'default' && $lastType !== 'variadic') {
                $errors->add("Variadic argument '{$element}' must appear after required and default arguments");
            }

            $lastType = $type;
        }

        return $errors;
    }

    /**
     * Determines the type of a signature element.
     */
    private function determineElementType(string $element): string
    {
        if (str_starts_with($element, '--')) {
            return 'flags';
        }

        if (str_contains($element, '*')) {
            return 'variadic';
        }

        if (str_contains($element, '=')) {
            return 'default';
        }

        return 'required';
    }

    /**
     * Validates the syntax of each token in the signature.
     */
    private function validateTokenSyntax(
        StringTypedCollection $elements,
        StringTypedCollection $errors,
        StringTypedCollection $suggestions
    ): void {
        $patterns = [
            'enum' => '/^::[a-zA-Z_][a-zA-Z0-9_]*->\[[^\]]+\](?:=[^ ]+)?$/',
            'default' => '/^[a-zA-Z_][a-zA-Z0-9_]*=(?:[^=]+|\?)$/',
            'variadic' => '/^[a-zA-Z_][a-zA-Z0-9_]*\*$/',
            'flag' => '/^--[a-zA-Z_][a-zA-Z0-9_-]*$/',
            'required' => '/^[a-zA-Z_][a-zA-Z0-9_]*$/',
        ];

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                $sourcePattern = '/^[a-zA-Z_][a-zA-Z0-9_\-]*$/';
                if (! preg_match($sourcePattern, $element)) {
                    $errors->add("Invalid source name: '{$element}'");
                    $suggestions->add('Use only letters, numbers, underscores and hyphens for source name');
                }

                continue;
            }

            $isValid = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $element)) {
                    $isValid = true;
                    break;
                }
            }

            if (! $isValid) {
                $errors->add("Invalid token syntax: '{$element}'");
                $suggestions->add('Check the syntax: required ({name}), default ({name=value}), variadic ({name*}), flag ({--flag}), enum (::name->[values])');
            }
        }
    }

    /**
     * Validates that there are no duplicate argument names in the signature.
     */
    private function validateDuplicateArguments(
        StringTypedCollection $elements,
        StringTypedCollection $errors,
        StringTypedCollection $suggestions
    ): void {
        $seen = [];

        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue;
            }

            // ✅ Normaliser pour les enums aussi
            $normalizedName = $element;
            if (str_starts_with($normalizedName, '::')) {
                $normalizedName = substr($normalizedName, 2);
            }
            $normalizedName = ltrim($normalizedName, '--');
            $normalizedName = rtrim($normalizedName, '*');
            $normalizedName = explode('=', $normalizedName)[0];
            $normalizedName = explode('->', $normalizedName)[0];

            if (isset($seen[$normalizedName])) {
                $errors->add("Duplicate argument name: '{$normalizedName}'");
                $suggestions->add("Rename or remove duplicate argument '{$normalizedName}'");
            }

            $seen[$normalizedName] = true;
        }
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

        // ✅ Build EnumCollection
        $enum = new EnumCollection;
        foreach ($data['enum'] ?? [] as $name => $enumData) {
            $enum->add(new EnumRecord(
                name: $name,
                value: $enumData['value'] ?? null,
                allowed_values: StringTypedCollection::from($enumData['allowed_values'] ?? []),
                default_value: $enumData['default_value'] ?? null,
                value_state: $enumData['value_state'] ?? ValueState::OPTIONAL,
            ));
        }

        $standardKeys = ['source', 'required', 'default', 'variadic', 'flags', 'enum'];
        $customData = array_filter(
            $data,
            fn ($key) => ! in_array($key, $standardKeys, true),
            ARRAY_FILTER_USE_KEY
        );

        return new ParsedSignatureRecord(
            source: $data['source'] ?? '',
            required: $required,
            default: $default,
            variadic: $variadic,
            flags: $flags,
            enum: $enum,
            custom_data: new StrictDataObject($customData),
        );
    }
}
