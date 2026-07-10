<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Parsers\Customs;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;

/**
 * Extracts custom key-value pairs from a query using <key="value"> syntax.
 *
 * Syntax: <key="value"> or <key='value'>
 * They are extracted and removed from the query for further parsing.
 *
 * @example
 * Signature: 'send {recipient} {--verbose}'
 * Query: 'send John --verbose <greeting="Hello World"> <later="goodby">'
 * Result: ['greeting' => 'Hello World', 'later' => 'goodby']
 */
final class CustomTagParser implements ParserInterface
{
    private const OPEN_TAG = '<';

    private const CLOSE_TAG = '>';

    /**
     * {@inheritDoc}
     */
    public function parse(array $signature, array $query): ParsedResultRecord
    {
        $customData = [];
        $remainingQuery = [];
        $inTag = false;
        $tagBuffer = [];

        foreach ($query as $token) {
            if (str_starts_with($token, self::OPEN_TAG)) {
                $inTag = true;
                $tagBuffer = [];
                $token = substr($token, 1);
            }

            if ($inTag) {
                if (str_ends_with($token, self::CLOSE_TAG)) {
                    $token = substr($token, 0, -1);
                    $tagBuffer[] = $token;

                    $tagContent = implode(' ', $tagBuffer);
                    $parsed = $this->parseCustomTag($tagContent);
                    if ($parsed !== null) {
                        $customData[$parsed['key']] = $parsed['value'];
                    }

                    $tagBuffer = [];
                    $inTag = false;

                    continue;
                }

                $tagBuffer[] = $token;

                continue;
            }

            $remainingQuery[] = $token;
        }

        if ($inTag && ! empty($tagBuffer)) {
            $tagContent = implode(' ', $tagBuffer);
            $parsed = $this->parseCustomTag($tagContent);
            if ($parsed !== null) {
                $customData[$parsed['key']] = $parsed['value'];
            }
        }

        return ParsedResultRecord::from([
            'data' => $customData,
            'signature' => $signature,
            'query' => $remainingQuery,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $signature, array $query): ValidationResultRecord
    {
        $errors = new StringTypedCollection;
        $suggestions = new StringTypedCollection;

        $inTag = false;
        $tagBuffer = [];

        foreach ($query as $token) {
            if (str_starts_with($token, self::OPEN_TAG)) {
                $inTag = true;
                $tagBuffer = [];
                $token = substr($token, 1);
            }

            if ($inTag) {
                if (str_ends_with($token, self::CLOSE_TAG)) {
                    $token = substr($token, 0, -1);
                    $tagBuffer[] = $token;

                    $tagContent = implode(' ', $tagBuffer);
                    $parsed = $this->parseCustomTag($tagContent);
                    if ($parsed === null) {
                        $errors->add("Invalid custom tag syntax: <{$tagContent}>");
                        $suggestions->add("Use format: <key=\"value\"> or <key='value'>");
                    }

                    $tagBuffer = [];
                    $inTag = false;

                    continue;
                }

                $tagBuffer[] = $token;

                continue;
            }
        }

        if ($inTag) {
            $errors->add('Unclosed custom tag');
            $suggestions->add('Close the tag with >');
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
    public function getTokenPattern(): string
    {
        return '/^<[a-zA-Z_][a-zA-Z0-9_]*=(?:"[^"]*"|\'[^\']*\'|[^>]+)>$/';
    }

    /**
     * Parses a custom tag and extracts key-value pair.
     *
     * @param  string  $tag  The tag content (e.g., 'greeting="Hello World"')
     * @return array{key: string, value: string}|null
     */
    private function parseCustomTag(string $tag): ?array
    {
        $parts = explode('=', $tag, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            return null;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        return ['key' => $key, 'value' => $value];
    }
}
