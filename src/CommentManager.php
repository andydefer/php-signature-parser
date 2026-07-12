<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser;

/**
 * Manages extraction and reattachment of comments in command signatures.
 *
 * Comments are defined with '#' followed by a quoted string after the argument:
 * - {name}#'comment' becomes {name}
 * - {name*>[values]}#'comment' becomes {name*>[values]}
 * - ::name->[values]#'comment' becomes ::name->[values]
 * - {--flag}#'comment' becomes {--flag}
 *
 * Comments can use single quotes (') or double quotes (").
 */
final class CommentManager
{
    /** @var array<string, string> Map of argument name to comment */
    private array $comments = [];

    /**
     * Removes comments from a signature and stores them for later use.
     *
     * @param  string  $signature  The signature with comments
     * @return string The signature without comments
     */
    public function extractComments(string $signature): string
    {
        $this->comments = [];

        // Pattern pour les arguments entre accolades avec commentaire
        // {name}#'comment' ou {name*>[values]}#'comment'
        $pattern = '/\{([^}]+)\}#\s*([\'"])([^\'"]*)\2/';
        $cleaned = preg_replace_callback($pattern, function ($matches) {
            $content = $matches[1];
            $comment = $matches[3];

            // Extraire le nom simple pour la clé
            $name = $content;
            // Enlever le '*' pour les variadics
            $name = rtrim($name, '*');
            // Enlever la partie '[values]' pour les variadics restreints
            if (str_contains($name, '*>')) {
                $name = substr($name, 0, strpos($name, '*>'));
            }
            // Enlever la partie '=valeur' pour les defaults
            $name = explode('=', $name)[0];

            $this->comments[$name] = $comment;

            return '{'.$content.'}';
        }, $signature);

        // Pattern pour les enums ::name->[values]#'comment'
        $pattern = '/(::[a-zA-Z_][a-zA-Z0-9_]*->\[[^\]]+\](?:=[^ ]+)?)#\s*([\'"])([^\'"]*)\2/';
        $cleaned = preg_replace_callback($pattern, function ($matches) {
            $token = $matches[1];
            $comment = $matches[3];

            // Extraire le nom simple pour la clé
            $name = substr($token, 2); // Enlever '::'
            $name = substr($name, 0, strpos($name, '->'));

            $this->comments[$name] = $comment;

            return $token;
        }, $cleaned);

        // Pattern pour les flags {--flag}#'comment'
        $pattern = '/\{--([^}]+)\}#\s*([\'"])([^\'"]*)\2/';
        $cleaned = preg_replace_callback($pattern, function ($matches) {
            $name = $matches[1];
            $comment = $matches[3];
            $this->comments['--'.$name] = $comment;

            return '{--'.$name.'}';
        }, $cleaned);

        return $cleaned;
    }

    /**
     * Returns the comment for a given argument name.
     *
     * @param  string  $name  The argument name
     * @return string|null The comment or null if none exists
     */
    public function getComment(string $name): ?string
    {
        return $this->comments[$name] ?? null;
    }

    /**
     * Returns all extracted comments.
     *
     * @return array<string, string> Map of argument name to comment
     */
    public function getAllComments(): array
    {
        return $this->comments;
    }

    /**
     * Checks if an argument has a comment.
     *
     * @param  string  $name  The argument name
     */
    public function hasComment(string $name): bool
    {
        return isset($this->comments[$name]);
    }

    /**
     * Resets the comment store.
     */
    public function reset(): void
    {
        $this->comments = [];
    }
}
