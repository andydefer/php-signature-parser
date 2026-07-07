<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\SignatureParser\Contracts\ParserInterface;
use AndyDefer\SignatureParser\Records\ParsedResultRecord;
use AndyDefer\SignatureParser\SignatureParser;
use PHPUnit\Framework\TestCase;

final class SignatureParserTest extends TestCase
{
    public function test_parses_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
        $query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame('dist', $result->default->last()->value);
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadic->first()->values->toArray());
        $this->assertSame(['home', 'data', 'models'], $result->variadic->last()->values->toArray());
        $this->assertTrue($result->options->first()->value);
        $this->assertFalse($result->options->last()->value);
    }

    public function test_extracts_signature_elements(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $parser = new SignatureParser;

        $result = $parser->extractSignatureElements($signature);

        $this->assertSame(['backup', 'source', 'destination', 'format=zip', '--force'], $result->toArray());
    }

    public function test_extracts_query_elements(): void
    {
        $query = 'backup /var/www /backup tar.gz [cache, logs] --force';
        $parser = new SignatureParser;

        $result = $parser->extractQueryElements($query);

        $this->assertSame(['backup', '/var/www', '/backup', 'tar.gz', '[cache, logs]', '--force'], $result->toArray());
    }

    public function test_parses_without_optional_elements(): void
    {
        $signature = 'backup {source} {destination}';
        $query = 'backup /var/www /backup';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertCount(0, $result->default);
        $this->assertCount(0, $result->variadic);
        $this->assertCount(0, $result->options);
    }

    public function test_parses_with_only_default_values(): void
    {
        $signature = 'backup {format=zip} {output=dist}';
        $query = 'backup tar.gz';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame('dist', $result->default->last()->value);
    }

    public function test_parses_with_only_options(): void
    {
        $signature = 'backup {--force} {--verbose}';
        $query = 'backup --force';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertTrue($result->options->first()->value);
        $this->assertFalse($result->options->last()->value);
    }

    public function test_parses_with_variadic_only(): void
    {
        $signature = 'backup {excludes*}';
        $query = 'backup [cache, logs, tmp]';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadic->first()->values->toArray());
    }

    public function test_handles_empty_query(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $query = 'backup';

        $parser = new SignatureParser;
        $result = $parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('', $result->required->first()->value);
        $this->assertSame('', $result->required->last()->value);
        $this->assertSame('zip', $result->default->first()->value);
        $this->assertFalse($result->options->first()->value);
    }

    public function test_removes_and_adds_parsers(): void
    {
        $parser = new SignatureParser;
        $initialCount = count($parser->getParsers());

        $parser->removeParser('AndyDefer\SignatureParser\Parsers\OptionsParser');
        $this->assertCount($initialCount - 1, $parser->getParsers());

        $parser->addParser(new class implements ParserInterface
        {
            public function parse(array $signature, array $query): ParsedResultRecord
            {
                return ParsedResultRecord::from([
                    'data' => ['custom' => 'value'],
                    'signature' => $signature,
                    'query' => $query,
                ]);
            }
        });
        $this->assertCount($initialCount, $parser->getParsers());
    }
}
