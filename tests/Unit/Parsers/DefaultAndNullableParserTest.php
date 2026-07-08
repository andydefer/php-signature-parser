<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\DefaultAndNullableParser;
use PHPUnit\Framework\TestCase;

final class DefaultAndNullableParserTest extends TestCase
{
    private DefaultAndNullableParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DefaultAndNullableParser;
    }

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

    public function test_handles_empty_default_value(): void
    {
        $signature = ['format='];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_empty_default_value_with_no_query_value(): void
    {
        $signature = ['format='];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertArrayNotHasKey('format', $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_nullable_argument_without_default(): void
    {
        $signature = ['format?'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_nullable_argument_with_no_query_value(): void
    {
        $signature = ['format?'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertArrayHasKey('format', $result->data->toArray()['default']);
        $this->assertNull($result->data->toArray()['default']['format']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_nullable_argument_with_empty_query_value(): void
    {
        $signature = ['format?'];
        $query = [''];

        $result = $this->parser->parse($signature, $query);

        $this->assertArrayHasKey('format', $result->data->toArray()['default']);
        $this->assertNull($result->data->toArray()['default']['format']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_mixed_default_and_nullable_arguments(): void
    {
        $signature = ['format=zip', 'output?', 'compression=9'];
        $query = ['tar.gz', 'high'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame([
            'format' => 'tar.gz',
            'output' => 'high',
            'compression' => '9',
        ], $result->data->toArray()['default']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_nullable_with_default_value_and_override(): void
    {
        $signature = ['format=zip'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
    }

    public function test_stops_at_variadic_bracket_with_nullable(): void
    {
        $signature = ['format?', 'excludes*'];
        $query = ['tar.gz', '[cache, logs]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame(['excludes*'], $result->signature->toArray());
        $this->assertSame(['[cache, logs]'], $result->query->toArray());
    }

    public function test_stops_at_option_with_nullable(): void
    {
        $signature = ['format?', '--force'];
        $query = ['tar.gz', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['default']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }
}
