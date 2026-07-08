<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\FlagParser;
use PHPUnit\Framework\TestCase;

final class FlagParserTest extends TestCase
{
    private FlagParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FlagParser;
    }

    public function test_extracts_flags(): void
    {
        $signature = ['--force', '--verbose'];
        $query = ['--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->flags->force);
        $this->assertFalse($result->data->flags->verbose);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_all_flags_when_present(): void
    {
        $signature = ['--force', '--verbose', '--all'];
        $query = ['--force', '--verbose', '--all'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->flags->force);
        $this->assertTrue($result->data->flags->verbose);
        $this->assertTrue($result->data->flags->all);
    }

    public function test_handles_no_flags(): void
    {
        $signature = ['source', 'destination'];
        $query = ['/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame([], $result->data->flags);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }

    public function test_flag_not_found_in_query(): void
    {
        $signature = ['--force', '--verbose'];
        $query = ['--verbose'];

        $result = $this->parser->parse($signature, $query);

        $this->assertFalse($result->data->flags->force);
        $this->assertTrue($result->data->flags->verbose);
    }

    public function test_preserves_non_flag_elements(): void
    {
        $signature = ['source', '--force', 'destination'];
        $query = ['/var/www', '--force', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->flags->force);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }

    public function test_handles_multiple_flags_with_other_elements(): void
    {
        $signature = ['--force', 'source', '--verbose', 'destination'];
        $query = ['--force', '/var/www', '/backup'];

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->data->flags->force);
        $this->assertFalse($result->data->flags->verbose);
        $this->assertSame(['source', 'destination'], $result->signature->toArray());
        $this->assertSame(['/var/www', '/backup'], $result->query->toArray());
    }

    public function test_handles_flags_with_empty_query(): void
    {
        $signature = ['--force', '--verbose'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertFalse($result->data->flags->force);
        $this->assertFalse($result->data->flags->verbose);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }
}
