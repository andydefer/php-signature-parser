<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\SourceParser;
use PHPUnit\Framework\TestCase;

final class SourceParserTest extends TestCase
{
    private SourceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SourceParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_source_from_signature_and_query(): void
    {
        $signature = ['backup', 'source', 'destination'];
        $query = ['backup', '/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->data->source);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }

    public function test_returns_empty_source_when_signature_empty(): void
    {
        $signature = [];
        $query = ['backup', '/var/www'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('', $result->data->source);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame(['/var/www'], $result->query->toArray());
    }

    public function test_removes_first_element_from_signature_and_query(): void
    {
        $signature = ['git', 'commit', '--all'];
        $query = ['git', 'commit', '--all'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('git', $result->data->source);
        $this->assertSame(['commit', '--all'], $result->signature->toArray());
        $this->assertSame(['commit', '--all'], $result->query->toArray());
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_with_valid_signature_and_query(): void
    {
        $signature = ['backup', 'source'];
        $query = ['backup', '/var/www'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_fails_for_empty_signature(): void
    {
        $signature = [];
        $query = ['backup'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Missing source', $result->errors->first());
        $this->assertStringContainsString('Add a command name', $result->suggestions->first());
    }

    public function test_validation_fails_for_empty_query(): void
    {
        $signature = ['backup'];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Missing query', $result->errors->first());
        $this->assertStringContainsString('Provide a query', $result->suggestions->first());
    }

    public function test_validation_fails_for_empty_both(): void
    {
        $signature = [];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors);
        $this->assertCount(2, $result->suggestions);
    }
}
