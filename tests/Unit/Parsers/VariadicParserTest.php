<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\VariadicParser;
use PHPUnit\Framework\TestCase;

final class VariadicParserTest extends TestCase
{
    private VariadicParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VariadicParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_variadic_arguments(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs, tmp]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['cache', 'logs', 'tmp'], $result->data->variadic['excludes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_variadic_arguments_with_spaces(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs, tmp]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['cache', 'logs', 'tmp'], $result->data->variadic['excludes']);
    }

    public function test_handles_empty_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame([], $result->data->variadic['excludes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_stops_at_option(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['cache', 'logs'], $result->data->variadic['excludes']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_multiple_variadic_arguments(): void
    {
        $signature = ['excludes*', 'includes*'];
        $query = ['[cache, logs]', '[src, tests]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['cache', 'logs'], $result->data->variadic['excludes']);
        $this->assertSame(['src', 'tests'], $result->data->variadic['includes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_with_valid_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_with_multiple_variadics(): void
    {
        $signature = ['excludes*', 'includes*'];
        $query = ['[cache, logs]', '[src, tests]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_with_empty_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_variadic_without_signature(): void
    {
        $signature = ['--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Variadic', $result->errors->first());
        $this->assertStringContainsString('Add a variadic argument', $result->suggestions->first());
    }

    public function test_validation_passes_when_variadic_defined_but_not_used(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_empty_value_in_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, , logs]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Empty value', $result->errors->first());
        $this->assertStringContainsString('Remove empty', $result->suggestions->first());
    }

    public function test_validation_handles_variadic_with_options(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_provides_suggestions_for_missing_variadic_signature(): void
    {
        $signature = ['--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $suggestions = $result->suggestions->toArray();
        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('Add a variadic argument', $suggestions[0]);
    }

    public function test_validation_passes_for_variadic_with_single_value(): void
    {
        $signature = ['excludes*'];
        $query = ['[file1.txt]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }
}
