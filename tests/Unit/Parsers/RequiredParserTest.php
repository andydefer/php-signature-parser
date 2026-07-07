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

    public function test_extracts_required_arguments(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->required->source);
        $this->assertSame('/backup', $result->data->required->destination);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_ignores_default_arguments(): void
    {
        $signature = ['source', 'format=zip'];
        $query = ['/var/www', 'zip'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->required->source);
        $this->assertSame(['format=zip'], $result->signature->toArray());
        $this->assertSame(['zip'], $result->query->toArray());
    }

    public function test_ignores_variadic_arguments(): void
    {
        $signature = ['source', 'excludes*'];
        $query = ['/var/www', '[cache, logs]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->required->source);
        $this->assertSame(['excludes*'], $result->signature->toArray());
        $this->assertSame(['[cache, logs]'], $result->query->toArray());
    }

    public function test_ignores_options(): void
    {
        $signature = ['source', '--force'];
        $query = ['/var/www', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/var/www', $result->data->required->source);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_empty_query(): void
    {
        $signature = ['source', 'destination'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('', $result->data->required->source);
        $this->assertSame('', $result->data->required->destination);
    }
}
