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
}
