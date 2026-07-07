<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\OptionsParser;
use PHPUnit\Framework\TestCase;

final class OptionsParserTest extends TestCase
{
    private OptionsParser $parser;

    protected function setUp(): void
    {
        $this->parser = new OptionsParser;
    }

    public function test_extracts_options(): void
    {
        $signature = ['--force', '--verbose'];
        $query = ['--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->options->force);
        $this->assertFalse($result->data->options->verbose);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_all_options_when_present(): void
    {
        $signature = ['--force', '--verbose', '--all'];
        $query = ['--force', '--verbose', '--all'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->options->force);
        $this->assertTrue($result->data->options->verbose);
        $this->assertTrue($result->data->options->all);
    }

    public function test_handles_no_options(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame([], $result->data->options);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }

    public function test_option_not_found_in_query(): void
    {
        $signature = ['--force', '--verbose'];
        $query = ['--verbose'];

        $result = $this->parser->parse($signature, $query);

        $this->assertFalse($result->data->options->force);
        $this->assertTrue($result->data->options->verbose);
    }

    public function test_preserves_non_option_elements(): void
    {
        $signature = ['source', '--force', 'destination'];
        $query = ['/var/www', '--force', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->options->force);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }
}
