<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Parsers\NullableParser;
use PHPUnit\Framework\TestCase;

final class NullableParserTest extends TestCase
{
    private NullableParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NullableParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_nullable_argument_with_value(): void
    {
        $signature = ['format?'];
        $query = ['tar.gz'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['format' => 'tar.gz'], $result->data->toArray()['nullable']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_nullable_argument_with_no_value(): void
    {
        $signature = ['format?'];
        $query = [];

        $result = $this->parser->parse($signature, $query);

        $this->assertArrayHasKey('format', $result->data->toArray()['nullable']);
        $this->assertNull($result->data->toArray()['nullable']['format']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_nullable_argument_with_empty_string(): void
    {
        $signature = ['format?'];
        $query = [''];

        $result = $this->parser->parse($signature, $query);

        $this->assertArrayHasKey('format', $result->data->toArray()['nullable']);
        $this->assertNull($result->data->toArray()['nullable']['format']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_multiple_nullable_arguments(): void
    {
        $signature = ['env?', 'port?'];
        $query = ['staging', '8080'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['env' => 'staging', 'port' => '8080'], $result->data->toArray()['nullable']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_multiple_nullable_with_missing_values(): void
    {
        $signature = ['env?', 'port?'];
        $query = ['staging'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['env' => 'staging', 'port' => null], $result->data->toArray()['nullable']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_handles_nullable_with_options(): void
    {
        $signature = ['env?', '--force'];
        $query = ['staging', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['env' => 'staging'], $result->data->toArray()['nullable']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_nullable_stops_at_variadic(): void
    {
        $signature = ['env?', 'files*'];
        $query = ['staging', '[file1, file2]'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['env' => 'staging'], $result->data->toArray()['nullable']);
        $this->assertSame(['files*'], $result->signature->toArray());
        $this->assertSame(['[file1, file2]'], $result->query->toArray());
    }

    public function test_handles_nullable_stops_at_flag(): void
    {
        $signature = ['env?', '--force'];
        $query = ['staging', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['env' => 'staging'], $result->data->toArray()['nullable']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_for_nullable_with_value(): void
    {
        $signature = ['format?'];
        $query = ['tar.gz'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_for_nullable_without_value(): void
    {
        $signature = ['format?'];
        $query = [];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_for_nullable_with_empty_string(): void
    {
        $signature = ['format?'];
        $query = [''];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_for_multiple_nullables(): void
    {
        $signature = ['env?', 'port?'];
        $query = ['staging', '8080'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_for_nullable_with_missing_values(): void
    {
        $signature = ['env?', 'port?'];
        $query = ['staging'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_for_nullable_with_mixed_signature(): void
    {
        $signature = ['env?', 'source', '--force'];
        $query = ['staging', '/var/www', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_returns_empty_collections_for_valid_input(): void
    {
        $signature = ['format?'];
        $query = ['tar.gz'];

        $result = $this->parser->validate($signature, $query);

        $this->assertInstanceOf(StringTypedCollection::class, $result->errors);
        $this->assertInstanceOf(StringTypedCollection::class, $result->suggestions);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }
}
