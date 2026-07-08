<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\DefaultParser;
use PHPUnit\Framework\TestCase;

final class DefaultParserTest extends TestCase
{
    private DefaultParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DefaultParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_default_arguments(): void
    {
        $signature = ['format=zip', 'output=dist'];
        $query = ['tar.gz', 'build'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz', 'output' => 'build'], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_keeps_default_value_when_no_query_value_provided(): void
    {
        $signature = ['format=zip', 'output=dist'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz', 'output' => 'dist'], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_stops_at_variadic_bracket(): void
    {
        $signature = ['format=zip', 'excludes*'];
        $query = ['tar.gz', '[cache, logs]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame(['excludes*'], $result->signature->toArray());
        $this->assertSame(['[cache, logs]'], $result->query->toArray());
    }

    public function test_stops_at_option(): void
    {
        $signature = ['format=zip', '--force'];
        $query = ['tar.gz', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_multiple_default_arguments(): void
    {
        $signature = ['format=zip', 'compression=9', 'backup=true'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame([
            'format' => 'tar.gz',
            'compression' => '9',
            'backup' => 'true',
        ], $result->data->toArray()['default']);
    }

    public function test_handles_default_with_override(): void
    {
        $signature = ['format=zip'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
    }

    public function test_handles_no_query_values(): void
    {
        $signature = ['format=zip', 'output=dist'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'zip', 'output' => 'dist'], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_throws_exception_for_empty_default_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot have empty value');

        $signature = ['format='];
        $query = ['tar.gz'];

        $this->parser->parse($signature, $query);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_for_valid_defaults(): void
    {
        $signature = ['format=zip', 'output=dist'];
        $query = ['tar.gz', 'build'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_for_defaults_with_missing_query(): void
    {
        $signature = ['format=zip', 'output=dist'];
        $query = ['tar.gz'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_empty_default_value(): void
    {
        $signature = ['format='];

        $result = $this->parser->validate($signature, []);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('empty value', $result->errors->first());
        $this->assertStringContainsString('nullable', $result->suggestions->first());
    }

    public function test_validation_fails_for_multiple_empty_defaults(): void
    {
        $signature = ['format=', 'output='];

        $result = $this->parser->validate($signature, []);

        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors);
        $this->assertCount(2, $result->suggestions);
    }

    public function test_validation_passes_for_mixed_signature(): void
    {
        $signature = ['format=zip', 'source', '--force'];
        $query = ['tar.gz', '/var/www', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_returns_suggestions_for_empty_default(): void
    {
        $signature = 'backup {source} {destination} {format=zip}';
        $query = 'backup /var/www ~ tar.gz';
        $signature = ['format='];

        $result = $this->parser->validate($signature, []);

        $this->assertFalse($result->isValid);
        $suggestions = $result->suggestions->toArray();
        $this->assertContains("Use 'format=?' for nullable instead of 'format='", $suggestions);
    }
}
