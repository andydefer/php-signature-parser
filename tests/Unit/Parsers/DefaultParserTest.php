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
}
