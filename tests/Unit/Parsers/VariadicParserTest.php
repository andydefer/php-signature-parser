<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\VariadicParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VariadicParserTest extends TestCase
{
    private VariadicParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VariadicParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_extracts_variadic_arguments(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs, tmp]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertArrayHasKey('excludes', $variadics);
        $this->assertSame(['cache', 'logs', 'tmp'], $variadics['excludes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_extracts_variadic_arguments_with_spaces(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs, tmp]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertSame(['cache', 'logs', 'tmp'], $variadics['excludes']);
    }

    public function test_handles_empty_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame([], $variadics['excludes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    public function test_stops_at_option(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame(['cache', 'logs'], $variadics['excludes']);
        $this->assertSame(['--force'], $result->signature->toArray());
        $this->assertSame(['--force'], $result->query->toArray());
    }

    public function test_handles_multiple_variadic_arguments(): void
    {
        $signature = ['excludes*', 'includes*'];
        $query = ['[cache, logs]', '[src, tests]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(2, $variadics);
        $this->assertArrayHasKey('excludes', $variadics);
        $this->assertArrayHasKey('includes', $variadics);
        $this->assertSame(['cache', 'logs'], $variadics['excludes']);
        $this->assertSame(['src', 'tests'], $variadics['includes']);
        $this->assertSame([], $result->signature->toArray());
        $this->assertSame([], $result->query->toArray());
    }

    // ==================== RESTRICTED VARIADIC TESTS ====================

    public function test_restricted_variadic_with_valid_values(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin,editor]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertArrayHasKey('roles', $variadics);
        $this->assertSame(['admin', 'editor'], $variadics['roles']);
    }

    public function test_restricted_variadic_throws_exception_on_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "guest" not allowed for "roles"');

        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin,guest]'];

        $this->parser->parse($signature, $query);
    }

    public function test_restricted_variadic_throws_exception_on_multiple_invalid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "guest" not allowed for "roles"');

        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[guest,unknown]'];

        $this->parser->parse($signature, $query);
    }

    public function test_restricted_variadic_accepts_single_value(): void
    {
        $signature = ['format*>[json,xml,yaml]'];
        $query = ['[json]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame(['json'], $variadics['format']);
    }

    public function test_restricted_variadic_with_empty_restrictions(): void
    {
        $signature = ['roles*>[]'];
        $query = ['[admin,editor]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame(['admin', 'editor'], $variadics['roles']);
    }

    public function test_restricted_variadic_with_spaces_in_definition(): void
    {
        $signature = ['roles*>[admin, editor, viewer]'];
        $query = ['[admin,editor]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame(['admin', 'editor'], $variadics['roles']);
    }

    public function test_restricted_variadic_with_spaces_in_query(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin, editor]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertArrayHasKey('roles', $variadics);
        $this->assertSame(['admin', 'editor'], $variadics['roles']);
    }

    public function test_restricted_variadic_with_flags(): void
    {
        $signature = ['roles*>[admin,editor,viewer]', '--verbose'];
        $query = ['[admin]', '--verbose'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertSame(['admin'], $variadics['roles']);
        $this->assertSame(['--verbose'], $result->signature->toArray());
        $this->assertSame(['--verbose'], $result->query->toArray());
    }

    public function test_restricted_variadic_removes_token_from_signature_and_query(): void
    {
        $signature = ['command', 'roles*>[admin,editor,viewer]', '--verbose'];
        $query = ['command', '[admin,editor]', '--verbose'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['command', '--verbose'], $result->signature->toArray());
        $this->assertSame(['command', '--verbose'], $result->query->toArray());
    }

    public function test_mixed_variadic_types(): void
    {
        $signature = ['roles*>[admin,editor]', 'tags*'];
        $query = ['[admin]', '[tag1,tag2]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(2, $variadics);
        $this->assertArrayHasKey('roles', $variadics);
        $this->assertArrayHasKey('tags', $variadics);
        $this->assertSame(['admin'], $variadics['roles']);
        $this->assertSame(['tag1', 'tag2'], $variadics['tags']);
    }

    public function test_restricted_variadic_with_underscores_in_name(): void
    {
        $signature = ['user_role*>[admin,editor,viewer]'];
        $query = ['[admin,editor]'];

        $result = $this->parser->parse($signature, $query);

        $variadics = $result->data->variadic->toArray();
        $this->assertCount(1, $variadics);
        $this->assertArrayHasKey('user_role', $variadics);
        $this->assertSame(['admin', 'editor'], $variadics['user_role']);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_with_valid_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, logs]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_with_multiple_variadics(): void
    {
        $signature = ['excludes*', 'includes*'];
        $query = ['[cache, logs]', '[src, tests]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_with_empty_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_variadic_without_signature(): void
    {
        $signature = ['--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Variadic', $result->errors->first());
        $this->assertStringContainsString('Add a variadic argument', $result->suggestions->first());
    }

    public function test_validation_passes_when_variadic_defined_but_not_used(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_empty_value_in_variadic(): void
    {
        $signature = ['excludes*'];
        $query = ['[cache, , logs]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Empty value', $result->errors->first());
        $this->assertStringContainsString('Remove empty', $result->suggestions->first());
    }

    public function test_validation_handles_variadic_with_options(): void
    {
        $signature = ['excludes*', '--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_provides_suggestions_for_missing_variadic_signature(): void
    {
        $signature = ['--force'];
        $query = ['[cache, logs]', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $suggestions = $result->suggestions->toArray();
        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('Add a variadic argument', $suggestions[0]);
    }

    public function test_validation_passes_for_variadic_with_single_value(): void
    {
        $signature = ['excludes*'];
        $query = ['[file1.txt]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    // ==================== RESTRICTED VALIDATION TESTS ====================

    public function test_validation_passes_for_restricted_variadic_with_valid_values(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin,editor]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_restricted_variadic_with_invalid_value(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin,guest]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'guest' not allowed for 'roles'", $result->errors->first());
        $this->assertStringContainsString('admin, editor, viewer', $result->suggestions->first());
    }

    public function test_validation_fails_for_restricted_variadic_with_multiple_invalid_values(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[guest,unknown]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
    }

    public function test_validation_fails_for_restricted_variadic_with_empty_allowed_values(): void
    {
        $signature = ['roles*>[]'];
        $query = ['[admin]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('no allowed values', $result->errors->first());
    }

    public function test_validation_passes_for_restricted_variadic_with_single_value(): void
    {
        $signature = ['format*>[json,xml,yaml]'];
        $query = ['[json]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_restricted_variadic_with_invalid_single_value(): void
    {
        $signature = ['format*>[json,xml,yaml]'];
        $query = ['[html]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'html' not allowed for 'format'", $result->errors->first());
        $this->assertStringContainsString('json, xml, yaml', $result->suggestions->first());
    }

    public function test_validation_passes_for_restricted_variadic_with_spaces_in_query(): void
    {
        $signature = ['roles*>[admin,editor,viewer]'];
        $query = ['[admin, editor]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_passes_for_multiple_restricted_variadics(): void
    {
        $signature = ['roles*>[admin,editor]', 'format*>[json,xml]'];
        $query = ['[admin,editor]', '[json]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_multiple_restricted_variadics_with_invalid_values(): void
    {
        $signature = ['roles*>[admin,editor]', 'format*>[json,xml]'];
        $query = ['[admin,guest]', '[html]'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
    }

    // ==================== TOKEN PATTERN TESTS ====================

    public function test_get_token_pattern_matches_simple_variadic(): void
    {
        $pattern = $this->parser->getTokenPattern();
        $this->assertMatchesRegularExpression($pattern, 'excludes*');
        $this->assertMatchesRegularExpression($pattern, 'files*');
        $this->assertMatchesRegularExpression($pattern, 'user_files*');
    }

    public function test_get_token_pattern_matches_restricted_variadic(): void
    {
        $pattern = $this->parser->getTokenPattern();
        $this->assertMatchesRegularExpression($pattern, 'roles*>[admin,editor,viewer]');
        $this->assertMatchesRegularExpression($pattern, 'format*>[json,xml]');
        $this->assertMatchesRegularExpression($pattern, 'user_role*>[admin,editor]');
        $this->assertMatchesRegularExpression($pattern, 'roles*>[]');
    }

    public function test_get_token_pattern_rejects_invalid_tokens(): void
    {
        $pattern = $this->parser->getTokenPattern();

        // ❌ Tokens invalides
        $this->assertDoesNotMatchRegularExpression($pattern, '123*');
        $this->assertDoesNotMatchRegularExpression($pattern, '*');
        $this->assertDoesNotMatchRegularExpression($pattern, 'roles*[admin]');
        $this->assertDoesNotMatchRegularExpression($pattern, 'roles>[admin]');
        $this->assertDoesNotMatchRegularExpression($pattern, 'role!s*');
        $this->assertDoesNotMatchRegularExpression($pattern, 'role s*');
        $this->assertDoesNotMatchRegularExpression($pattern, 'roles*>[admin, editor');

        // ✅ Ces tokens sont valides
        $this->assertMatchesRegularExpression($pattern, 'invalid*');
        $this->assertMatchesRegularExpression($pattern, 'test*');
        $this->assertMatchesRegularExpression($pattern, 'user_files*');
        $this->assertMatchesRegularExpression($pattern, 'roles*>[admin,editor]');
        $this->assertMatchesRegularExpression($pattern, 'format*>[json,xml,yaml]');
    }
}
