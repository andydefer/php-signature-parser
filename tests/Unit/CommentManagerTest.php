<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\SignatureParser\CommentManager;
use PHPUnit\Framework\TestCase;

final class CommentManagerTest extends TestCase
{
    private CommentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new CommentManager;
    }

    // ==================== EXTRACT COMMENTS TESTS ====================

    public function test_extract_comments_from_required_argument(): void
    {
        $signature = 'command {name}#"The user name" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {name} {--verbose}', $clean);
        $this->assertTrue($this->manager->hasComment('name'));
        $this->assertSame('The user name', $this->manager->getComment('name'));
    }

    public function test_extract_comments_from_default_argument(): void
    {
        $signature = 'command {format=zip}#"The output format" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {format=zip} {--verbose}', $clean);
        $this->assertTrue($this->manager->hasComment('format'));
        $this->assertSame('The output format', $this->manager->getComment('format'));
    }

    public function test_extract_comments_from_restricted_variadic(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {roles*>[admin,editor,viewer]} {--verbose}', $clean);
        $this->assertTrue($this->manager->hasComment('roles'));
        $this->assertSame('The allowed roles', $this->manager->getComment('roles'));
    }

    public function test_extract_comments_from_enum(): void
    {
        $signature = 'command ::level->[low,medium,high]=medium#"The priority level" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command ::level->[low,medium,high]=medium {--verbose}', $clean);
        $this->assertTrue($this->manager->hasComment('level'));
        $this->assertSame('The priority level', $this->manager->getComment('level'));
    }

    public function test_extract_comments_from_flag(): void
    {
        $signature = 'command {--force}#"Force the operation" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {--force} {--verbose}', $clean);
        $this->assertTrue($this->manager->hasComment('--force'));
        $this->assertSame('Force the operation', $this->manager->getComment('--force'));
    }

    public function test_extract_comments_using_single_quotes(): void
    {
        $signature = "command {name}#'The user name' {--verbose}";
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {name} {--verbose}', $clean);
        $this->assertSame('The user name', $this->manager->getComment('name'));
    }

    public function test_extract_multiple_comments(): void
    {
        $signature = 'command {name}#"User name" {role*>[admin,editor]}#"Roles" {--verbose}#"Show details"';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {name} {role*>[admin,editor]} {--verbose}', $clean);

        $comments = $this->manager->getAllComments();
        $this->assertCount(3, $comments);
        $this->assertSame('User name', $comments['name']);
        $this->assertSame('Roles', $comments['role']);
        $this->assertSame('Show details', $comments['--verbose']);
    }

    public function test_extract_comments_with_spaces(): void
    {
        $signature = 'command {name} # "The user name" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        // Avec espaces, le commentaire n'est pas extrait car la syntaxe est stricte
        $this->assertSame('command {name} # "The user name" {--verbose}', $clean);
        $this->assertFalse($this->manager->hasComment('name'));
    }

    public function test_extract_comments_with_special_characters(): void
    {
        $signature = 'command {name}#"User name (e.g., John Doe)" {--force}#"Force mode (use with caution!)"';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {name} {--force}', $clean);
        $this->assertSame('User name (e.g., John Doe)', $this->manager->getComment('name'));
        $this->assertSame('Force mode (use with caution!)', $this->manager->getComment('--force'));
    }

    public function test_extract_comments_mixed_with_and_without_comments(): void
    {
        $signature = 'command {name}#"User name" {format=zip} {role*>[admin,editor]}#"Roles" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {name} {format=zip} {role*>[admin,editor]} {--verbose}', $clean);

        $this->assertTrue($this->manager->hasComment('name'));
        $this->assertFalse($this->manager->hasComment('format'));
        $this->assertTrue($this->manager->hasComment('role'));
        $this->assertFalse($this->manager->hasComment('--verbose'));
    }

    public function test_get_comment_returns_null_for_nonexistent_token(): void
    {
        $signature = 'command {name}#"User name" {--verbose}';
        $this->manager->extractComments($signature);

        $this->assertNull($this->manager->getComment('nonexistent'));
        $this->assertFalse($this->manager->hasComment('nonexistent'));
    }

    public function test_get_all_comments_returns_empty_array_when_no_comments(): void
    {
        $signature = 'command {name} {format=zip} {--verbose}';
        $this->manager->extractComments($signature);

        $this->assertEmpty($this->manager->getAllComments());
    }

    public function test_extract_comments_on_enum_with_default_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle#"The user skill level"';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('set-level ::level->[beginner,middle,master]=middle', $clean);
        $this->assertSame('The user skill level', $this->manager->getComment('level'));
    }

    public function test_extract_comments_on_enum_required(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*#"The required level"';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('set-level ::level->[beginner,middle,master]=*', $clean);
        $this->assertSame('The required level', $this->manager->getComment('level'));
    }

    public function test_extract_comments_on_enum_optional(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=?#"The optional level"';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('set-level ::level->[beginner,middle,master]=?', $clean);
        $this->assertSame('The optional level', $this->manager->getComment('level'));
    }

    public function test_extract_comments_on_variadic_simple(): void
    {
        $signature = 'command {files*}#"The files to process" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command {files*} {--verbose}', $clean);
        $this->assertSame('The files to process', $this->manager->getComment('files'));
    }

    public function test_reset_clears_all_comments(): void
    {
        $signature = 'command {name}#"User name" {--verbose}';
        $this->manager->extractComments($signature);

        $this->assertCount(1, $this->manager->getAllComments());

        $this->manager->reset();

        $this->assertEmpty($this->manager->getAllComments());
        $this->assertNull($this->manager->getComment('name'));
        $this->assertFalse($this->manager->hasComment('name'));
    }

    public function test_extract_comments_preserves_signature_without_comments(): void
    {
        $signature = 'command {name} {format=zip} {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame($signature, $clean);
        $this->assertEmpty($this->manager->getAllComments());
    }

    public function test_extract_comments_on_enum_without_state(): void
    {
        // Enum sans '=state' est required par défaut
        $signature = 'command ::level->[low,medium,high]#"The level" {--verbose}';
        $clean = $this->manager->extractComments($signature);

        $this->assertSame('command ::level->[low,medium,high] {--verbose}', $clean);
        $this->assertSame('The level', $this->manager->getComment('level'));
    }
}
