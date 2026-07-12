<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\SignatureParser\SignatureDocumentor;
use PHPUnit\Framework\TestCase;

final class SignatureDocumentorTest extends TestCase
{
    // ==================== ARRAY FORMAT TESTS ====================

    public function test_generate_array_format_with_simple_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertSame('backup', $result['source']);
        $this->assertCount(2, $result['requireds']);
        $this->assertCount(1, $result['defaults']);
        $this->assertCount(0, $result['enums']);
        $this->assertCount(0, $result['variadics']);
        $this->assertCount(1, $result['flags']);
    }

    public function test_generate_array_format_with_comments(): void
    {
        $signature = 'backup {source}#"Source directory" {destination}#"Destination" {format=zip}#"Archive format" {--force}#"Force overwrite"';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertSame('backup', $result['source']);

        $this->assertArrayHasKey('comment', $result['requireds'][0]);
        $this->assertSame('Source directory', $result['requireds'][0]['comment']);
        $this->assertSame('Destination', $result['requireds'][1]['comment']);

        $this->assertArrayHasKey('comment', $result['defaults'][0]);
        $this->assertSame('Archive format', $result['defaults'][0]['comment']);

        $this->assertArrayHasKey('comment', $result['flags'][0]);
        $this->assertSame('Force overwrite', $result['flags'][0]['comment']);
    }

    public function test_generate_array_format_without_comments_omits_comment_key(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('comment', $result['requireds'][0]);
        $this->assertArrayNotHasKey('comment', $result['requireds'][1]);
        $this->assertArrayNotHasKey('comment', $result['defaults'][0]);
        $this->assertArrayNotHasKey('comment', $result['flags'][0]);
    }

    public function test_generate_array_format_with_enums(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle#"The skill level"';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertSame('set-level', $result['source']);
        $this->assertCount(1, $result['enums']);

        $enum = $result['enums'][0];
        $this->assertSame('level', $enum['name']);
        $this->assertSame(['beginner', 'middle', 'master'], $enum['allowed_values']);
        $this->assertSame('middle', $enum['default_value']);
        $this->assertFalse($enum['is_required']);
        $this->assertFalse($enum['is_optional']);
        $this->assertSame('The skill level', $enum['comment']);
    }

    public function test_generate_array_format_with_restricted_variadic(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles"';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertCount(1, $result['variadics']);

        $variadic = $result['variadics'][0];
        $this->assertSame('roles', $variadic['name']);
        $this->assertSame(['admin', 'editor', 'viewer'], $variadic['restrictions']);
        $this->assertSame('The allowed roles', $variadic['comment']);
    }

    public function test_generate_array_format_with_mixed_components(): void
    {
        $signature = 'deploy {environment}#"Target env" {version=latest}#"Version" ::level->[low,high]=low#"Priority" {excludes*}#"Excludes" {--force}#"Force" {--dry-run}#"Dry run"';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertSame('deploy', $result['source']);
        $this->assertCount(1, $result['requireds']);
        $this->assertCount(1, $result['defaults']);
        $this->assertCount(1, $result['enums']);
        $this->assertCount(1, $result['variadics']);
        $this->assertCount(2, $result['flags']);

        $this->assertSame('Target env', $result['requireds'][0]['comment']);
        $this->assertSame('Version', $result['defaults'][0]['comment']);
        $this->assertSame('Priority', $result['enums'][0]['comment']);
        $this->assertSame('Excludes', $result['variadics'][0]['comment']);
        $this->assertSame('Force', $result['flags'][0]['comment']);
        $this->assertSame('Dry run', $result['flags'][1]['comment']);
    }

    public function test_generate_array_format_restrictions_null_when_no_restrictions(): void
    {
        $signature = 'command {files*}';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertCount(1, $result['variadics']);
        $this->assertNull($result['variadics'][0]['restrictions']);
    }

    // ==================== MARKDOWN FORMAT TESTS ====================

    public function test_generate_markdown_format(): void
    {
        $signature = 'backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {--force}';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('# Commande : backup', $result);
        $this->assertStringContainsString('## Arguments requis', $result);
        $this->assertStringContainsString('| Nom | Description |', $result);
        $this->assertStringContainsString('| `source` | Source directory |', $result);
        $this->assertStringContainsString('| `destination` | — |', $result);
        $this->assertStringContainsString('## Arguments par défaut', $result);
        $this->assertStringContainsString('| `format` | `zip` | Archive format |', $result);
        $this->assertStringContainsString('## Flags', $result);
        $this->assertStringContainsString('| `--force` | — |', $result);
    }

    public function test_generate_markdown_format_with_enums(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle#"The skill level"';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('## Énumérations', $result);
        $this->assertStringContainsString('| `level` | `beginner, middle, master` | Défaut: `middle` | The skill level |', $result);
    }

    public function test_generate_markdown_format_with_restricted_variadic(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles"';
        $query = 'command [admin, editor]';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('## Arguments variadiques', $result);
        $this->assertStringContainsString('| `roles*` | `admin, editor, viewer` | The allowed roles |', $result);
    }

    // ==================== TEXT FORMAT TESTS ====================

    public function test_generate_text_format(): void
    {
        $signature = 'backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {--force}';

        $result = SignatureDocumentor::generate($signature, 'text');

        $this->assertIsString($result);
        $this->assertStringContainsString('COMMANDE: backup', $result);
        $this->assertStringContainsString('ARGUMENTS REQUIS:', $result);
        $this->assertStringContainsString('  source : Source directory', $result);
        $this->assertStringContainsString('  destination : —', $result);
        $this->assertStringContainsString('ARGUMENTS PAR DÉFAUT:', $result);
        $this->assertStringContainsString('  format (défaut: zip) : Archive format', $result);
        $this->assertStringContainsString('FLAGS:', $result);
        $this->assertStringContainsString('  --force : —', $result);
    }

    // ==================== JSON FORMAT TESTS ====================

    public function test_generate_json_format(): void
    {
        $signature = 'backup {source}#"Source directory" {destination} {format=zip}#"Archive format" {--force}';

        $result = SignatureDocumentor::generate($signature, 'json');

        $this->assertIsString($result);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertSame('backup', $decoded['source']);
        $this->assertSame('Source directory', $decoded['requireds'][0]['comment']);
        $this->assertSame('Archive format', $decoded['defaults'][0]['comment']);
    }

    // ==================== EDGE CASES ====================

    public function test_generate_with_signature_without_comments(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('comment', $result['requireds'][0]);
        $this->assertArrayNotHasKey('comment', $result['defaults'][0]);
        $this->assertArrayNotHasKey('comment', $result['flags'][0]);
    }

    public function test_generate_with_signature_with_mixed_comments(): void
    {
        $signature = 'backup {source}#"Source" {destination} {format=zip}#"Format" {excludes*} {--force}#"Force"';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('comment', $result['requireds'][0]);
        $this->assertArrayNotHasKey('comment', $result['requireds'][1]);
        $this->assertArrayHasKey('comment', $result['defaults'][0]);
        $this->assertArrayNotHasKey('comment', $result['variadics'][0]);
        $this->assertArrayHasKey('comment', $result['flags'][0]);
    }

    public function test_generate_with_invalid_format_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported format: xml');

        SignatureDocumentor::generate('backup {source}', 'xml');
    }

    public function test_generate_with_enum_required(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertTrue($result['enums'][0]['is_required']);
        $this->assertFalse($result['enums'][0]['is_optional']);
        $this->assertNull($result['enums'][0]['default_value']);
    }

    public function test_generate_with_enum_optional(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=?';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertFalse($result['enums'][0]['is_required']);
        $this->assertTrue($result['enums'][0]['is_optional']);
        $this->assertNull($result['enums'][0]['default_value']);
    }

    public function test_generate_with_restricted_variadic_empty_restrictions(): void
    {
        $signature = 'command {roles*>[]}';

        $result = SignatureDocumentor::generate($signature, 'array');

        $this->assertIsArray($result);
        $this->assertCount(1, $result['variadics']);
        $this->assertEmpty($result['variadics'][0]['restrictions']);
    }

    public function test_generate_markdown_without_requireds(): void
    {
        $signature = 'backup {format=zip} {--force}';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringNotContainsString('## Arguments requis', $result);
        $this->assertStringContainsString('## Arguments par défaut', $result);
        $this->assertStringContainsString('## Flags', $result);
    }

    public function test_generate_markdown_without_defaults(): void
    {
        $signature = 'backup {source} {destination} {--force}';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('## Arguments requis', $result);
        $this->assertStringNotContainsString('## Arguments par défaut', $result);
        $this->assertStringContainsString('## Flags', $result);
    }

    public function test_generate_markdown_without_flags(): void
    {
        $signature = 'backup {source} {destination} {format=zip}';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('## Arguments requis', $result);
        $this->assertStringContainsString('## Arguments par défaut', $result);
        $this->assertStringNotContainsString('## Flags', $result);
    }

    public function test_build_command_example_with_all_types(): void
    {
        $signature = 'backup {source} {destination} {format=zip} ::level->[low,high]=low {excludes*} {--force} {--verbose}';

        $result = SignatureDocumentor::generate($signature, 'markdown');

        $this->assertIsString($result);
        $this->assertStringContainsString('backup <source> <destination> [format=zip] [level:low|high=low] [excludes*] [--force] [--verbose]', $result);
    }
}
