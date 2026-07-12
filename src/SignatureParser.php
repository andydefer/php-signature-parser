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
 * - Variadic arguments: {name*} or {name*>[value1,value2,value3]} (restricted)
 * - Boolean flags: {--flag}
 * - Enum arguments: ::name->[value1,value2,value3]=default
 * - Custom tags: <key="value">
 * - Argument comments: {name}#'comment' or ::name->[values]#"comment" or {--flag}#'comment'
 *
 * @example
 * $parser = new SignatureParser();
 * $result = $parser->parse('greet {name} {--formal}', 'greet John --formal');
 * echo $result->source; // 'greet'
 * echo $result->requireds->first()->value; // 'John'
 */
final class SignatureParser implements ParserRegistryInterface, SignatureParserInterface
{
    /** @var array<ParserInterface> */
    private array $parsers = [];

    private CommentManager $commentManager;

    /**
     * Initializes the parser with the default chain of responsibility.
     */
    public function __construct()
    {
        $this->commentManager = new CommentManager;
        $this->addParser(new SourceParser);
        $this->addParser(new RequiredParser);
        $this->addParser(new EnumParser);
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
        // Extraire les commentaires de la signature
        $cleanSignature = $this->commentManager->extractComments($signature);

        $signatureElements = $this->extractSignatureElements($cleanSignature);
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
        // Extraire les commentaires de la signature
        $cleanSignature = $this->commentManager->extractComments($signature);

        $signatureElements = $this->extractSignatureElements($cleanSignature);
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
        // Extraire les commentaires de la signature
        $cleanSignature = $this->commentManager->extractComments($signature);

        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $elements = $this->extractSignatureElements($cleanSignature);

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
     * 5. Enum arguments: ::name->[values]
     * 6. Variadic arguments: {name*} or {name*>[value1,value2]}
     * 7. Flags: {--flag}
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
                $errors->add("Required argument '{$element}' must appear before default, enum, variadic or flags");
            }

            if ($type === 'default' && $lastType !== 'source' && $lastType !== 'required' && $lastType !== 'default') {
                $errors->add("Default argument '{$element}' must appear after required arguments and before enum, variadic or flags");
            }

            if ($type === 'enum' && $lastType !== 'source' && $lastType !== 'required' && $lastType !== 'default' && $lastType !== 'enum') {
                $errors->add("Enum argument '{$element}' must appear after default arguments and before variadic or flags");
            }

            if ($type === 'variadic' && $lastType !== 'source' && $lastType !== 'required' && $lastType !== 'default' && $lastType !== 'enum' && $lastType !== 'variadic') {
                $errors->add("Variadic argument '{$element}' must appear after required, default and enum arguments");
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

        if (str_starts_with($element, '::')) {
            return 'enum';
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
            'variadic_restricted' => '/^[a-zA-Z_][a-zA-Z0-9_]*\*>\s*\[[^\]]*\]\s*$/',
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
                $suggestions->add('Check the syntax: required ({name}), default ({name=value}), variadic ({name*}) or restricted variadic ({name*>[value1,value2]}), flag ({--flag}), enum (::name->[values])');
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

            // Normaliser pour les enums, flags, variadics, etc.
            $normalizedName = $element;

            // Enlever le préfixe '::' pour les enums
            if (str_starts_with($normalizedName, '::')) {
                $normalizedName = substr($normalizedName, 2);
            }

            // Enlever le préfixe '--' pour les flags
            $normalizedName = ltrim($normalizedName, '--');

            // Enlever le '*' pour les variadics
            $normalizedName = rtrim($normalizedName, '*');

            // Enlever la partie '>[values]' pour les variadics restreints
            if (str_contains($normalizedName, '*>')) {
                $normalizedName = substr($normalizedName, 0, strpos($normalizedName, '*>'));
            }

            // Enlever la partie '=valeur' pour les defaults
            $normalizedName = explode('=', $normalizedName)[0];

            // Enlever la partie '->[values]' pour les enums
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
        $requireds = new ArgumentCollection;
        foreach ($data['requireds'] ?? [] as $name => $value) {
            $comment = $this->commentManager->getComment($name);
            $requireds->add(new ArgumentRecord($name, $value, $comment));
        }

        $defaults = new ArgumentCollection;
        foreach ($data['defaults'] ?? [] as $name => $value) {
            $comment = $this->commentManager->getComment($name);
            $defaults->add(new ArgumentRecord($name, $value, $comment));
        }

        $variadics = new VariadicArgumentCollection;
        $variadicData = $data['variadics'] ?? [];
        foreach ($variadicData as $name => $values) {
            $restrictions = new StringTypedCollection;
            if (isset($data['restrictions'][$name])) {
                $restrictions = StringTypedCollection::from($data['restrictions'][$name]);
            }

            $comment = $this->commentManager->getComment($name);
            $variadics->add(new VariadicArgumentRecord(
                $name,
                StringTypedCollection::from($values),
                $restrictions,
                $comment
            ));
        }

        $flags = new FlagCollection;
        foreach ($data['flags'] ?? [] as $name => $value) {
            $comment = $this->commentManager->getComment('--'.$name);
            $flags->add(new FlagRecord($name, $value, $comment));
        }

        // Build EnumCollection
        $enums = new EnumCollection;
        foreach ($data['enums'] ?? [] as $name => $enumData) {
            $comment = $this->commentManager->getComment($name);
            $enums->add(new EnumRecord(
                name: $name,
                value: $enumData['value'] ?? null,
                allowed_values: StringTypedCollection::from($enumData['allowed_values'] ?? []),
                default_value: $enumData['default_value'] ?? null,
                value_state: $enumData['value_state'] ?? ValueState::OPTIONAL,
                comment: $comment,
            ));
        }

        $standardKeys = ['source', 'requireds', 'defaults', 'variadics', 'restrictions', 'flags', 'enums'];
        $customData = array_filter(
            $data,
            fn ($key) => ! in_array($key, $standardKeys, true),
            ARRAY_FILTER_USE_KEY
        );

        return new ParsedSignatureRecord(
            source: $data['source'] ?? '',
            requireds: $requireds,
            defaults: $defaults,
            variadics: $variadics,
            flags: $flags,
            enums: $enums,
            custom_data: new StrictDataObject($customData),
        );
    }
}
