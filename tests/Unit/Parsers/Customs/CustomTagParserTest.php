<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers\Customs;

use AndyDefer\SignatureParser\Parsers\Customs\CustomTagParser;
use PHPUnit\Framework\TestCase;

final class CustomTagParserTest extends TestCase
{
    private CustomTagParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CustomTagParser;
    }

    // ==================== TESTS: Parse ====================

    public function test_parse_extracts_single_tag_with_double_quotes(): void
    {
        $signature = ['send', '{recipient}', '{--verbose}'];
        $query = ['send', 'John', '--verbose', '<greeting="Hello World">'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('greeting', $data);
        $this->assertSame('Hello World', $data['greeting']);

        // Verify the tag was removed from the query
        $this->assertSame($signature, $result->signature->toArray());
        $this->assertSame(['send', 'John', '--verbose'], $result->query->toArray());
    }

    public function test_parse_extracts_multiple_tags(): void
    {
        $signature = ['send', '{recipient}', '{--verbose}'];
        $query = ['send', 'John', '--verbose', '<greeting="Hello World">', '<later="goodby">'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('greeting', $data);
        $this->assertSame('Hello World', $data['greeting']);
        $this->assertArrayHasKey('later', $data);
        $this->assertSame('goodby', $data['later']);

        $this->assertSame(['send', 'John', '--verbose'], $result->query->toArray());
    }

    public function test_parse_with_single_quotes(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', "<message='Hello World'>"];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Hello World', $data['message']);
        $this->assertSame(['send', 'John'], $result->query->toArray());
    }

    public function test_parse_with_tag_containing_spaces(): void
    {
        $signature = ['deploy', '{--force}'];
        $query = ['deploy', '<message="Deploy to staging environment">', '--force'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Deploy to staging environment', $data['message']);
        $this->assertSame(['deploy', '--force'], $result->query->toArray());
    }

    public function test_parse_with_empty_value(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<later="">'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('later', $data);
        $this->assertSame('', $data['later']);
        $this->assertSame(['send', 'John'], $result->query->toArray());
    }

    public function test_parse_with_no_tags(): void
    {
        $signature = ['greet', '{name}'];
        $query = ['greet', 'John'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertEmpty($data);
        $this->assertSame($signature, $result->signature->toArray());
        $this->assertSame($query, $result->query->toArray());
    }

    public function test_parse_preserves_signature(): void
    {
        $signature = ['deploy', '{environment}', '{--force}'];
        $query = ['deploy', 'staging', '<version="1.2.3">', '--force'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame($signature, $result->signature->toArray());
    }

    // ==================== TESTS: Validate ====================

    public function test_validate_returns_valid_for_correct_syntax(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<greeting="Hello World">'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->suggestions);
    }

    public function test_validate_returns_invalid_for_unclosed_tag(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<greeting="Hello World"'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Unclosed custom tag', $result->errors->first());
        $this->assertStringContainsString('Close the tag with >', $result->suggestions->first());
    }

    public function test_validate_returns_invalid_for_invalid_key(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<123invalid="value">'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid custom tag syntax', $result->errors->first());
    }

    public function test_validate_returns_invalid_for_missing_equal_sign(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<greeting "Hello World">'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid custom tag syntax', $result->errors->first());
    }

    public function test_validate_returns_valid_with_multiple_valid_tags(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<greeting="Hello">', '<name="John">'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_returns_invalid_when_one_tag_is_invalid_among_multiple(): void
    {
        $signature = ['send', '{recipient}'];
        $query = ['send', 'John', '<greeting="Hello">', '<123invalid="value">'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid custom tag syntax', $result->errors->first());
    }

    // ==================== TESTS: getTokenPattern ====================

    public function test_get_token_pattern(): void
    {
        $pattern = $this->parser->getTokenPattern();

        $this->assertMatchesRegularExpression('/^\/.*\/$/', $pattern);

        // Test that the pattern matches valid tags
        $this->assertMatchesRegularExpression($pattern, '<greeting="Hello">');
        $this->assertMatchesRegularExpression($pattern, "<name='John'>");
        $this->assertMatchesRegularExpression($pattern, '<key="value with spaces">');

        // Test that the pattern doesn't match invalid tags
        $this->assertDoesNotMatchRegularExpression($pattern, '<greeting Hello>');
        $this->assertDoesNotMatchRegularExpression($pattern, '<123invalid="value">');
        $this->assertDoesNotMatchRegularExpression($pattern, 'greeting="Hello"');
    }
}
