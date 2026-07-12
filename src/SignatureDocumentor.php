<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;

/**
 * Generates documentation for a command signature.
 *
 * This class analyzes a signature and produces human-readable documentation
 * describing all its components: source, required arguments, default arguments,
 * enums, variadic arguments, flags, and their comments.
 *
 * @example
 * $markdown = SignatureDocumentor::generate('backup {source} {destination} {format=zip}#"Archive format" {--force}#"Force overwrite"');
 * echo $markdown;
 */
final class SignatureDocumentor
{
    /**
     * Generates complete documentation for a signature.
     *
     * @param  string  $signature  The command signature
     * @param  string  $format  The output format ('markdown', 'text', 'json', or 'array')
     * @return string|array The generated documentation
     */
    public static function generate(string $signature, string $format = 'markdown'): string|array
    {
        $structure = new SignatureStructureVO($signature);
        $data = self::extractData($structure, $signature);

        return match ($format) {
            'markdown' => self::toMarkdown($data),
            'text' => self::toText($data),
            'json' => self::toJson($data),
            'array' => self::toArray($data),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    /**
     * Extracts all data from the signature structure.
     *
     * @param  string  $rawSignature  The raw signature with comments
     * @return array<string, mixed>
     */
    private static function extractData(SignatureStructureVO $structure, string $rawSignature): array
    {
        $data = [
            'source' => $structure->getSource(),
            'requireds' => [],
            'defaults' => [],
            'enums' => [],
            'variadics' => [],
            'flags' => [],
        ];

        // Extraire les commentaires depuis la signature brute
        $commentManager = new CommentManager;
        $commentManager->extractComments($rawSignature);

        // Nettoyer la signature uniquement pour l'extraction des éléments
        $cleanSignature = $commentManager->extractComments($rawSignature);

        // Utiliser une méthode d'extraction qui ne nettoie pas les commentaires
        $elements = self::extractElementsWithoutClean($cleanSignature);

        // Parcourir les éléments pour identifier les types
        foreach ($elements as $index => $element) {
            if ($index === 0) {
                continue; // Source déjà traitée
            }

            $name = $element;

            // Enum
            if (str_starts_with($name, '::')) {
                $enumName = self::extractEnumName($name);
                $allowedValues = self::extractAllowedValues($name);
                [$defaultValue, $isRequired, $isOptional] = self::extractEnumState($name);

                $item = [
                    'name' => $enumName,
                    'allowed_values' => $allowedValues,
                    'default_value' => $defaultValue,
                    'is_required' => $isRequired,
                    'is_optional' => $isOptional,
                ];
                $comment = $commentManager->getComment($enumName);
                if ($comment !== null) {
                    $item['comment'] = $comment;
                }
                $data['enums'][] = $item;

                continue;
            }

            // Flag
            if (str_starts_with($name, '--')) {
                $flagName = ltrim($name, '--');
                $item = ['name' => $flagName];
                $comment = $commentManager->getComment('--'.$flagName);
                if ($comment !== null) {
                    $item['comment'] = $comment;
                }
                $data['flags'][] = $item;

                continue;
            }

            // Variadic
            if (str_contains($name, '*')) {
                $variadicName = self::extractVariadicName($name);
                $item = [
                    'name' => $variadicName,
                    'restrictions' => self::extractRestrictions($rawSignature, $variadicName),
                ];
                $comment = $commentManager->getComment($variadicName);
                if ($comment !== null) {
                    $item['comment'] = $comment;
                }
                $data['variadics'][] = $item;

                continue;
            }

            // Default
            if (str_contains($name, '=')) {
                [$defaultName, $defaultValue] = explode('=', $name, 2);
                $item = [
                    'name' => $defaultName,
                    'default' => $defaultValue,
                ];
                $comment = $commentManager->getComment($defaultName);
                if ($comment !== null) {
                    $item['comment'] = $comment;
                }
                $data['defaults'][] = $item;

                continue;
            }

            // Required
            $item = ['name' => $name];
            $comment = $commentManager->getComment($name);
            if ($comment !== null) {
                $item['comment'] = $comment;
            }
            $data['requireds'][] = $item;
        }

        return $data;
    }

    /**
     * Extracts elements from a signature without cleaning comments.
     *
     * @param  string  $signature  The signature
     * @return array<int, string>
     */
    private static function extractElementsWithoutClean(string $signature): array
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

        return $result;
    }

    /**
     * Extracts enum name from token.
     */
    private static function extractEnumName(string $token): string
    {
        $name = substr($token, 2);
        $name = substr($name, 0, strpos($name, '->'));

        return $name;
    }

    /**
     * Extracts allowed values from enum token.
     */
    private static function extractAllowedValues(string $token): array
    {
        if (preg_match('/\[([^\]]+)\]/', $token, $matches)) {
            return array_map('trim', explode(',', $matches[1]));
        }

        return [];
    }

    /**
     * Extracts enum state (default, required, optional).
     *
     * @return array{0: string|null, 1: bool, 2: bool}
     */
    private static function extractEnumState(string $token): array
    {
        $defaultValue = null;
        $isRequired = false;
        $isOptional = false;

        if (preg_match('/=([^*?]+)$/', $token, $matches)) {
            $defaultValue = $matches[1];
        } elseif (str_contains($token, '=*')) {
            $isRequired = true;
        } elseif (str_contains($token, '=?')) {
            $isOptional = true;
        }

        return [$defaultValue, $isRequired, $isOptional];
    }

    /**
     * Extracts variadic name from token.
     */
    private static function extractVariadicName(string $token): string
    {
        $name = str_replace('*', '', $token);
        if (str_contains($name, '>')) {
            $name = substr($name, 0, strpos($name, '>'));
        }

        return $name;
    }

    /**
     * Extracts restrictions for a variadic argument.
     *
     * @param  string  $signature  The raw signature
     * @param  string  $name  The variadic name
     * @return array<string>|null
     */
    private static function extractRestrictions(string $signature, string $name): ?array
    {
        $pattern = '/\{'.preg_quote($name, '/').'\*>\s*\[([^\]]*)\]\s*\}/';
        if (preg_match($pattern, $signature, $matches)) {
            $allowed = array_map('trim', explode(',', $matches[1]));

            return array_filter($allowed, fn ($v) => $v !== '');
        }

        return null;
    }

    /**
     * Converts data to array format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function toArray(array $data): array
    {
        return $data;
    }

    /**
     * Converts data to Markdown format.
     *
     * @param  array<string, mixed>  $data
     */
    private static function toMarkdown(array $data): string
    {
        $lines = [];

        // Titre
        $lines[] = '# Commande : '.$data['source'];
        $lines[] = '';
        $lines[] = '## Description';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = self::buildCommandExample($data);
        $lines[] = '```';
        $lines[] = '';

        // Arguments requis
        if (! empty($data['requireds'])) {
            $lines[] = '## Arguments requis';
            $lines[] = '';
            $lines[] = '| Nom | Description |';
            $lines[] = '|-----|-------------|';
            foreach ($data['requireds'] as $arg) {
                $comment = $arg['comment'] ?? '—';
                $lines[] = "| `{$arg['name']}` | {$comment} |";
            }
            $lines[] = '';
        }

        // Arguments par défaut
        if (! empty($data['defaults'])) {
            $lines[] = '## Arguments par défaut';
            $lines[] = '';
            $lines[] = '| Nom | Défaut | Description |';
            $lines[] = '|-----|--------|-------------|';
            foreach ($data['defaults'] as $arg) {
                $default = $arg['default'] ?? 'null';
                $comment = $arg['comment'] ?? '—';
                $lines[] = "| `{$arg['name']}` | `{$default}` | {$comment} |";
            }
            $lines[] = '';
        }

        // Enums
        if (! empty($data['enums'])) {
            $lines[] = '## Énumérations';
            $lines[] = '';
            $lines[] = '| Nom | Valeurs autorisées | État | Description |';
            $lines[] = '|-----|-------------------|------|-------------|';
            foreach ($data['enums'] as $enum) {
                $values = implode(', ', $enum['allowed_values']);
                if ($enum['is_required']) {
                    $state = 'Requis';
                } elseif ($enum['is_optional']) {
                    $state = 'Optionnel';
                } else {
                    $state = "Défaut: `{$enum['default_value']}`";
                }
                $comment = $enum['comment'] ?? '—';
                $lines[] = "| `{$enum['name']}` | `{$values}` | {$state} | {$comment} |";
            }
            $lines[] = '';
        }

        // Variadics
        if (! empty($data['variadics'])) {
            $lines[] = '## Arguments variadiques';
            $lines[] = '';
            $lines[] = '| Nom | Restrictions | Description |';
            $lines[] = '|-----|--------------|-------------|';
            foreach ($data['variadics'] as $variadic) {
                $restrictions = $variadic['restrictions'] !== null && ! empty($variadic['restrictions'])
                    ? '`'.implode(', ', $variadic['restrictions']).'`'
                    : 'Aucune';
                $comment = $variadic['comment'] ?? '—';
                $lines[] = "| `{$variadic['name']}*` | {$restrictions} | {$comment} |";
            }
            $lines[] = '';
        }

        // Flags
        if (! empty($data['flags'])) {
            $lines[] = '## Flags';
            $lines[] = '';
            $lines[] = '| Nom | Description |';
            $lines[] = '|-----|-------------|';
            foreach ($data['flags'] as $flag) {
                $comment = $flag['comment'] ?? '—';
                $lines[] = "| `--{$flag['name']}` | {$comment} |";
            }
            $lines[] = '';
        }

        // Exemple complet
        $lines[] = '## Exemple complet';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = self::buildCommandExample($data);
        $lines[] = '```';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Converts data to plain text format.
     *
     * @param  array<string, mixed>  $data
     */
    private static function toText(array $data): string
    {
        $lines = [];

        $lines[] = 'COMMANDE: '.$data['source'];
        $lines[] = str_repeat('=', 50);
        $lines[] = '';

        if (! empty($data['requireds'])) {
            $lines[] = 'ARGUMENTS REQUIS:';
            foreach ($data['requireds'] as $arg) {
                $comment = $arg['comment'] ?? '—';
                $lines[] = "  {$arg['name']} : {$comment}";
            }
            $lines[] = '';
        }

        if (! empty($data['defaults'])) {
            $lines[] = 'ARGUMENTS PAR DÉFAUT:';
            foreach ($data['defaults'] as $arg) {
                $default = $arg['default'] ?? 'null';
                $comment = $arg['comment'] ?? '—';
                $lines[] = "  {$arg['name']} (défaut: {$default}) : {$comment}";
            }
            $lines[] = '';
        }

        if (! empty($data['enums'])) {
            $lines[] = 'ÉNUMÉRATIONS:';
            foreach ($data['enums'] as $enum) {
                $values = implode(', ', $enum['allowed_values']);
                if ($enum['is_required']) {
                    $state = 'Requis';
                } elseif ($enum['is_optional']) {
                    $state = 'Optionnel';
                } else {
                    $state = "Défaut: {$enum['default_value']}";
                }
                $comment = $enum['comment'] ?? '—';
                $lines[] = "  {$enum['name']} : {$values} [{$state}] - {$comment}";
            }
            $lines[] = '';
        }

        if (! empty($data['variadics'])) {
            $lines[] = 'ARGUMENTS VARIADIQUES:';
            foreach ($data['variadics'] as $variadic) {
                $restrictions = $variadic['restrictions'] !== null && ! empty($variadic['restrictions'])
                    ? '['.implode(', ', $variadic['restrictions']).']'
                    : 'aucune restriction';
                $comment = $variadic['comment'] ?? '—';
                $lines[] = "  {$variadic['name']}* : {$restrictions} - {$comment}";
            }
            $lines[] = '';
        }

        if (! empty($data['flags'])) {
            $lines[] = 'FLAGS:';
            foreach ($data['flags'] as $flag) {
                $comment = $flag['comment'] ?? '—';
                $lines[] = "  --{$flag['name']} : {$comment}";
            }
            $lines[] = '';
        }

        $lines[] = 'EXEMPLE:';
        $lines[] = '  '.self::buildCommandExample($data);
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Converts data to JSON format.
     *
     * @param  array<string, mixed>  $data
     */
    private static function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Builds a command example from the data.
     *
     * @param  array<string, mixed>  $data
     */
    private static function buildCommandExample(array $data): string
    {
        $parts = [$data['source']];

        // Requireds
        foreach ($data['requireds'] as $arg) {
            $parts[] = '<'.$arg['name'].'>';
        }

        // Defaults
        foreach ($data['defaults'] as $arg) {
            $parts[] = '['.$arg['name'].'='.($arg['default'] ?? '?').']';
        }

        // Enums
        foreach ($data['enums'] as $enum) {
            $values = implode('|', $enum['allowed_values']);
            if ($enum['is_required']) {
                $parts[] = '<'.$enum['name'].':'.$values.'>';
            } elseif ($enum['is_optional']) {
                $parts[] = '['.$enum['name'].':'.$values.']';
            } else {
                $default = $enum['default_value'];
                $parts[] = '['.$enum['name'].':'.$values.'='.$default.']';
            }
        }

        // Variadics
        foreach ($data['variadics'] as $variadic) {
            $parts[] = '['.$variadic['name'].'*]';
        }

        // Flags
        foreach ($data['flags'] as $flag) {
            $parts[] = '[--'.$flag['name'].']';
        }

        return implode(' ', $parts);
    }
}
