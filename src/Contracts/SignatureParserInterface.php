<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Defines the contract for a signature parser that extracts structured data
 * from CLI command signatures and queries.
 *
 * The parser handles:
 * - Source extraction (command name)
 * - Required arguments: {name}
 * - Default arguments: {name=value}
 * - Nullable arguments: {name=?}
 * - Variadic arguments: {name*}
 * - Options: {--flag}
 */
interface SignatureParserInterface
{
    /**
     * Parses a command signature and query into a structured record.
     *
     * The result contains:
     * - 'source': The command name
     * - 'required': Array of required arguments (name => value)
     * - 'default': Array of default arguments (name => value)
     * - 'variadic': Array of variadic arguments (name => array of values)
     * - 'flags': Array of flags (name => boolean)
     *
     * @param  string  $signature  The command signature (e.g., 'backup {source} {destination} {--force}')
     * @param  string  $query  The actual command query (e.g., 'backup /var/www /backup --force')
     * @return ParsedSignatureRecord The structured result
     *
     * @throws \InvalidArgumentException If the signature order is invalid
     */
    public function parse(string $signature, string $query): ParsedSignatureRecord;

    /**
     * Extracts all elements from a signature string.
     *
     * Removes curly braces and returns the raw elements.
     *
     * @param  string  $signature  The signature string
     * @return StringTypedCollection The extracted elements
     *
     * @example
     * $elements = $parser->extractSignatureElements('backup {source} {destination} {--force}');
     * // ['backup', 'source', 'destination', '--force']
     */
    public function extractSignatureElements(string $signature): StringTypedCollection;

    /**
     * Extracts all elements from a query string.
     *
     * Preserves variadic arguments in square brackets.
     *
     * @param  string  $query  The query string
     * @return StringTypedCollection The extracted elements
     *
     * @example
     * $elements = $parser->extractQueryElements('backup /var/www /backup [cache, logs] --force');
     * // ['backup', '/var/www', '/backup', '[cache, logs]', '--force']
     */
    public function extractQueryElements(string $query): StringTypedCollection;

    /**
     * Validates a query against a signature.
     *
     * Checks for:
     * - Missing required arguments
     * - Extra unknown arguments
     * - Invalid flags
     * - Invalid variadic format
     * - Order violations
     *
     * @param  string  $signature  The command signature
     * @param  string  $query  The command query to validate
     * @return ValidationResultRecord The validation result with errors and suggestions
     */
    public function validate(string $signature, string $query): ValidationResultRecord;

    /**
     * Checks if a query is valid against a signature.
     *
     * @param  string  $signature  The command signature
     * @param  string  $query  The command query to validate
     * @return bool True if the query is valid
     */
    public function isValid(string $signature, string $query): bool;

    /**
     * Returns all validation errors for a query.
     *
     * @param  string  $signature  The command signature
     * @param  string  $query  The command query to validate
     * @return StringTypedCollection List of error messages
     */
    public function getValidationErrors(string $signature, string $query): StringTypedCollection;

    /**
     * Validates the signature structure itself.
     *
     * Checks for:
     * - Correct order of arguments (required → default → variadic → flags)
     * - Valid token syntax
     * - No duplicate argument names
     * - No reserved names
     *
     * @param  string  $signature  The command signature to validate
     * @return ValidationResultRecord The validation result with errors and suggestions
     *
     * @example
     * $result = $parser->validateSignature('backup {source} {format=zip} {excludes*} {--force}');
     * if ($result->isValid) {
     *     echo 'Signature is valid';
     * }
     */
    public function validateSignature(string $signature): ValidationResultRecord;

    /**
     * Checks if a signature structure is valid.
     *
     * @param  string  $signature  The command signature to validate
     * @return bool True if the signature is valid
     *
     * @example
     * if ($parser->isSignatureValid('backup {source} {format=zip}')) {
     *     echo 'Valid signature';
     * }
     */
    public function isSignatureValid(string $signature): bool;
}
