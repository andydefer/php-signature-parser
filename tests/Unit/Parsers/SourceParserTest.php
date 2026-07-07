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
        // La requête n'ayant pas de signature pour lui donner un sens,
        // le parser retire le premier élément qui aurait dû être la source
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
}
