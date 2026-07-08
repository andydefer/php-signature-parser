<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Formatters;

/**
 * Formatter to recursively replace '^' with spaces in all string values.
 *
 * Special handling:
 * - `?` → null (explicit null value)
 * - `??` → `?` (escaped question mark)
 */
final class TextFormatter
{
    /**
     * Recursively replaces '^' with space in all string values of an array.
     *
     * Special handling:
     * - `?` → null (explicit null value)
     * - `??` → `?` (escaped question mark)
     *
     * @param  array<mixed>  $data  The data to format
     * @return array<mixed> The formatted data with '^' replaced by spaces
     *
     * @example
     * $data = [
     *     'name' => 'John^Doe',
     *     'address' => '123^Main^Street',
     *     'env' => '?',
     *     'tags' => ['PHP^8.4', '??'],
     *     'metadata' => [
     *         'message' => 'Hello^World!'
     *     ]
     * ];
     *
     * $formatted = TextFormatter::format($data);
     * // [
     * //     'name' => 'John Doe',
     * //     'address' => '123 Main Street',
     * //     'env' => null,
     * //     'tags' => ['PHP 8.4', '?'],
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
                // D'abord gérer les cas spéciaux
                if ($value === '?') {
                    $result[$key] = null;
                } elseif ($value === '??') {
                    $result[$key] = '?';
                } else {
                    // Puis remplacer ^ par espace
                    $result[$key] = str_replace('^', ' ', $value);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Formats a single string value.
     *
     * Special handling:
     * - `?` → null (explicit null value)
     * - `??` → `?` (escaped question mark)
     *
     * @param  string  $value  The string to format
     * @return string|null The formatted string with '^' replaced by spaces
     */
    public static function formatString(string $value): ?string
    {
        if ($value === '?') {
            return null;
        }

        if ($value === '??') {
            return '?';
        }

        return str_replace('^', ' ', $value);
    }
}
