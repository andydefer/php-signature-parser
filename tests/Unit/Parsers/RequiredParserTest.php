<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\RequiredParser;
use PHPUnit\Framework\TestCase;

final class RequiredParserTest extends TestCase
{
    private RequiredParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RequiredParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_required_arguments(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->requireds->source);
        $this->assertSame('/backup', $result->data->requireds->destination);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_ignores_default_arguments(): void
    {
        $signature = ['source', 'format=zip'];
        $query = ['/var/www', 'zip'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->requireds->source);
        $this->assertSame(['format=zip'], $result->signature->toArray());
        $this->assertSame(['zip'], $result->query->toArray());
    }

    public function test_ignores_variadic_arguments(): void
    {
        $signature = ['source', 'excludes*'];
        $query = ['/var/www', '[cache, logs]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->requireds->source);
        $this->assertSame(['excludes*'], $result->signature->toArray());
        $this->assertSame(['[cache, logs]'], $result->query->toArray());
    }

    public function test_ignores_options(): void
    {
        $signature = ['source', '--force'];
        $query = ['/var/www', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->requireds->source);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_empty_query(): void
    {
        $signature = ['source', 'destination'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('', $result->data->requireds->source);
        $this->assertSame('', $result->data->requireds->destination);
    }

    public function test_handles_missing_query_values(): void
    {
        $signature = ['source', 'destination', 'format'];
        $query = ['/var/www'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->requireds->source);
        $this->assertSame('', $result->data->requireds->destination);
        $this->assertSame('', $result->data->requireds->format);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_for_all_required_present(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_fails_for_missing_required(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('destination', $result->errors->first());
        $this->assertStringContainsString('Provide', $result->suggestions->first());
    }

    public function test_validation_fails_for_multiple_missing_required(): void
    {
        $signature = ['source', 'destination', 'format'];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(3, $result->errors);
        $this->assertCount(3, $result->suggestions);
    }

    public function test_validation_fails_for_missing_required_with_mixed_query(): void
    {
        $signature = ['source', 'destination', 'format'];
        $query = ['/var/www'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors);
        $this->assertStringContainsString('destination', $result->errors->first());
        $this->assertStringContainsString('format', $result->errors->last());
    }

    public function test_validation_ignores_non_required_elements(): void
    {
        $signature = ['source', 'format=zip', '--force'];
        $query = ['/var/www', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_with_empty_query_when_no_required(): void
    {
        $signature = ['format=zip', '--force'];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_handles_mixed_required_and_optional(): void
    {
        $signature = ['source', 'destination', 'format=zip'];
        $query = ['/var/www'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('destination', $result->errors->first());
    }

    public function test_validation_provides_suggestions_for_missing_required(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $suggestions = $result->suggestions->toArray();
        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('destination', $suggestions[0]);
    }

    public function test_validation_provides_suggestions_for_multiple_missing(): void
    {
        $signature = ['source', 'destination', 'format'];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $suggestions = $result->suggestions->toArray();
        $this->assertCount(3, $suggestions);
        $this->assertStringContainsString('source', $suggestions[0]);
        $this->assertStringContainsString('destination', $suggestions[1]);
        $this->assertStringContainsString('format', $suggestions[2]);
    }

    public function test_validation_returns_correct_error_count_for_partial_query(): void
    {
        $signature = ['source', 'destination', 'format', 'type'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors);
        $this->assertStringContainsString('format', $result->errors->first());
        $this->assertStringContainsString('type', $result->errors->last());
    }
}
