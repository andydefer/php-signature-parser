<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Formatters;

/**
 * Recursively formats string values by replacing '^' with spaces.
 *
 * Handles special tokens:
 * - `?`  → `null` (explicitly null)
 * - `~`  → `null` (skip argument, use default)
 * - `??` → `?`   (escaped question mark)
 * - `~~` → `~`   (escaped tilde)
 *
 * This formatter is used after parsing to normalize values before they are
 * stored in the final record. It works recursively on nested arrays.
 */
final class TextFormatter
{
    /**
     * Recursively formats all string values in an array.
     *
     * Special tokens are handled first, then '^' characters are replaced with spaces.
     *
     * @param  array<mixed>  $data  The data to format
     * @return array<mixed> The formatted data
     *
     * @example
     * $data = [
     *     'name'    => 'John^Doe',
     *     'address' => '123^Main^Street',
     *     'env'     => '?',
     *     'skip'    => '~',
     *     'tags'    => ['PHP^8.4', '??'],
     *     'metadata' => ['message' => 'Hello^World!']
     * ];
     *
     * $formatted = TextFormatter::format($data);
     * // [
     * //     'name'    => 'John Doe',
     * //     'address' => '123 Main Street',
     * //     'env'     => null,
     * //     'skip'    => null,
     * //     'tags'    => ['PHP 8.4', '?'],
     * //     'metadata' => ['message' => 'Hello World!']
     * // ]
     */
    public static function format(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::format($value);

                continue;
            }

            if (! is_string($value)) {
                $result[$key] = $value;

                continue;
            }

            $result[$key] = self::formatString($value);
        }

        return $result;
    }

    /**
     * Formats a single string value.
     *
     * Handles special tokens and replaces '^' with spaces.
     *
     * @param  string  $value  The string to format
     * @return string|null The formatted value, or null for special tokens
     */
    public static function formatString(string $value): ?string
    {
        return match ($value) {
            '?', '~' => null,
            '??' => '?',
            '~~' => '~',
            default => str_replace('^', ' ', $value),
        };
    }
}
