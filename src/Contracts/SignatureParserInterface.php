<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Contracts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Records\ParsedSignatureRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

interface SignatureParserInterface
{
    public function parse(string $signature, string $query): ParsedSignatureRecord;

    public function extractSignatureElements(string $signature): StringTypedCollection;

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
}
