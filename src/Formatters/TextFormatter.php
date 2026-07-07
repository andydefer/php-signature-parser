<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Formatters;

/**
 * Formatter to recursively replace '^' with spaces in all string values.
 */
final class TextFormatter
{
    /**
     * Recursively replaces '^' with space in all string values of an array.
     *
     * @param  array<mixed>  $data  The data to format
     * @return array<mixed> The formatted data with '^' replaced by spaces
     *
     * @example
     * $data = [
     *     'name' => 'John^Doe',
     *     'address' => '123^Main^Street',
     *     'tags' => ['PHP^8.4', 'Laravel'],
     *     'metadata' => [
     *         'message' => 'Hello^World!'
     *     ]
     * ];
     *
     * $formatted = TextFormatter::format($data);
     * // [
     * //     'name' => 'John Doe',
     * //     'address' => '123 Main Street',
     * //     'tags' => ['PHP 8.4', 'Laravel'],
     * //     'metadata' => [
     * //         'message' => 'Hello World!'
     * //     ]
     * // ]
     */
    public static function format(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::format($value);
            } elseif (is_string($value)) {
                $result[$key] = str_replace('^', ' ', $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Formats a single string value.
     *
     * @param  string  $value  The string to format
     * @return string The formatted string with '^' replaced by spaces
     */
    public static function formatString(string $value): string
    {
        return str_replace('^', ' ', $value);
    }
}
