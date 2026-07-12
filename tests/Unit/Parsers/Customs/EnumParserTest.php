<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Parsers;

use AndyDefer\SignatureParser\Parsers\EnumParser;
use PHPUnit\Framework\TestCase;

final class EnumParserTest extends TestCase
{
    private EnumParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EnumParser;
    }

    // ==================== TESTS: Parse ====================

    public function test_parse_extracts_enum_with_default_value(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle'];
        $query = ['set-level', 'master'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertSame('master', $data['enums']['level']['value']);
        $this->assertSame(['beginner', 'middle', 'master'], $data['enums']['level']['allowed_values']);
        $this->assertSame('middle', $data['enums']['level']['default_value']);
        $this->assertSame(['set-level'], $result->signature->toArray());
        $this->assertSame(['set-level'], $result->query->toArray());
    }

    public function test_parse_uses_default_value_when_not_provided(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle'];
        $query = ['set-level'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertSame('middle', $data['enums']['level']['value']);
        $this->assertSame(['set-level'], $result->query->toArray());
    }

    public function test_parse_handles_optional_enum_with_tilde(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=?'];
        $query = ['set-level', '~'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertNull($data['enums']['level']['value']);
        $this->assertSame(['set-level'], $result->query->toArray());
    }

    public function test_parse_handles_required_enum(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=*'];
        $query = ['set-level', 'beginner'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertSame('beginner', $data['enums']['level']['value']);
        $this->assertSame(['set-level'], $result->query->toArray());
    }

    public function test_parse_removes_enum_token_from_signature(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle', '--verbose'];
        $query = ['set-level', 'master', '--verbose'];

        $result = $this->parser->parse($signature, $query);

        $this->assertSame(['set-level', '--verbose'], $result->signature->toArray());
        $this->assertSame(['set-level', '--verbose'], $result->query->toArray());
    }

    public function test_parse_with_multiple_enums(): void
    {
        $signature = [
            'config',
            '::level->[low,medium,high]=medium',
            '::mode->[dev,staging,prod]=dev',
        ];
        $query = ['config', 'high', 'staging'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertSame('high', $data['enums']['level']['value']);
        $this->assertArrayHasKey('mode', $data['enums']);
        $this->assertSame('staging', $data['enums']['mode']['value']);
        $this->assertSame(['config'], $result->query->toArray());
        $this->assertSame(['config'], $result->signature->toArray());
    }

    public function test_parse_with_flags_and_enums(): void
    {
        $signature = [
            'deploy',
            '::env->[dev,staging,prod]=staging',
            '--force',
        ];
        $query = ['deploy', 'prod', '--force'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('env', $data['enums']);
        $this->assertSame('prod', $data['enums']['env']['value']);
        $this->assertSame(['deploy', '--force'], $result->signature->toArray());
        $this->assertSame(['deploy', '--force'], $result->query->toArray());
    }

    public function test_parse_with_enum_and_custom_tags(): void
    {
        $signature = [
            'send',
            '::priority->[low,medium,high]=medium',
            '--verbose',
        ];
        $query = ['send', 'high', '--verbose', '<user="admin">'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('priority', $data['enums']);
        $this->assertSame('high', $data['enums']['priority']['value']);
        $this->assertSame(['send', '--verbose', '<user="admin">'], $result->query->toArray());
        $this->assertSame(['send', '--verbose'], $result->signature->toArray());
    }

    public function test_parse_preserves_rest_of_signature(): void
    {
        $signature = ['deploy', '::env->[dev,staging,prod]=*', '{environment}'];
        $query = ['deploy', 'staging', 'prod'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('env', $data['enums']);
        $this->assertSame('staging', $data['enums']['env']['value']);
        $this->assertSame(['deploy', '{environment}'], $result->signature->toArray());
        $this->assertSame(['deploy', 'prod'], $result->query->toArray());
    }

    // ==================== TESTS: Validate ====================

    public function test_validate_returns_valid_for_correct_enum_value(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle'];
        $query = ['set-level', 'master'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEmpty($result->suggestions);
    }

    public function test_validate_returns_valid_for_default_value(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle'];
        $query = ['set-level'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_returns_valid_for_optional_with_tilde(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=?'];
        $query = ['set-level', '~'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_returns_invalid_for_invalid_enum_value(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=middle'];
        $query = ['set-level', 'expert'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Invalid value 'expert' for enum 'level'", $result->errors->first());
        $this->assertStringContainsString('beginner, middle, master', $result->suggestions->first());
    }

    public function test_validate_returns_invalid_for_required_missing(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=*'];
        $query = ['set-level'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Missing required enum value for', $result->errors->first());
        $this->assertStringContainsString('beginner, middle, master', $result->suggestions->first());
    }

    public function test_validate_returns_invalid_for_tilde_on_non_optional(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=*'];
        $query = ['set-level', '~'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Cannot use '~' for non-optional enum", $result->errors->first());
        $this->assertStringContainsString('beginner, middle, master', $result->suggestions->first());
    }

    public function test_validate_returns_invalid_for_invalid_default_value(): void
    {
        $signature = ['set-level', '::level->[beginner,middle,master]=expert'];
        $query = ['set-level'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Default value 'expert' for enum 'level' is not in allowed values", $result->errors->first());
        $this->assertStringContainsString('beginner, middle, master', $result->suggestions->first());
    }

    public function test_validate_with_multiple_enums_one_invalid(): void
    {
        $signature = [
            'config',
            '::level->[low,medium,high]=medium',
            '::mode->[dev,staging,prod]=dev',
        ];
        $query = ['config', 'high', 'invalid'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Invalid value 'invalid' for enum 'mode'", $result->errors->first());
    }

    public function test_validate_returns_valid_with_flags_and_enums(): void
    {
        $signature = [
            'deploy',
            '::env->[dev,staging,prod]=staging',
            '--force',
        ];
        $query = ['deploy', 'prod', '--force'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    // ==================== TESTS: getTokenPattern ====================

    public function test_get_token_pattern(): void
    {
        $pattern = $this->parser->getTokenPattern();

        $this->assertMatchesRegularExpression('/^\/.*\/$/', $pattern);

        // ✅ Test that the pattern matches valid enum tokens
        $this->assertMatchesRegularExpression($pattern, '::level->[beginner,middle,master]=middle');
        $this->assertMatchesRegularExpression($pattern, '::level->[beginner,middle,master]=*');
        $this->assertMatchesRegularExpression($pattern, '::level->[beginner,middle,master]=?');
        $this->assertMatchesRegularExpression($pattern, '::mode->[dev,staging,prod]');

        // ✅ Test that the pattern doesn't match invalid tokens
        $this->assertDoesNotMatchRegularExpression($pattern, 'level->[beginner,middle,master]=middle');
        $this->assertDoesNotMatchRegularExpression($pattern, '::level->[beginner,middle,master');
        $this->assertDoesNotMatchRegularExpression($pattern, '::level->beginner,middle,master=middle');
        $this->assertDoesNotMatchRegularExpression($pattern, '{::level->[beginner,middle,master]=middle}');
    }

    // ==================== TESTS: Edge Cases ====================

    public function test_parse_with_empty_allowed_values(): void
    {
        $signature = ['test', '::empty->[]=*'];
        $query = ['test', 'value'];

        // ✅ La validation doit échouer car il n'y a pas de valeurs autorisées
        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('has no allowed values', $result->errors->first());
    }

    public function test_parse_with_empty_string_value(): void
    {
        $signature = ['test', '::level->[a,,b]=*'];
        $query = ['test', 'a'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        // ✅ Les valeurs vides doivent être filtrées
        $this->assertSame(['a', 'b'], $data['enums']['level']['allowed_values']);
    }

    public function test_parse_with_single_allowed_value(): void
    {
        $signature = ['test', '::single->[only]=only'];
        $query = ['test', 'only'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('single', $data['enums']);
        $this->assertSame('only', $data['enums']['single']['value']);
        $this->assertSame(['only'], $data['enums']['single']['allowed_values']);
    }

    public function test_parse_with_enum_name_containing_underscores(): void
    {
        $signature = ['test', '::user_level->[beginner,middle,master]=middle'];
        $query = ['test', 'master'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('user_level', $data['enums']);
        $this->assertSame('master', $data['enums']['user_level']['value']);
    }

    public function test_parse_with_enum_containing_numbers(): void
    {
        $signature = ['test', '::level->[level1,level2,level3]=level1'];
        $query = ['test', 'level2'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertArrayHasKey('level', $data['enums']);
        $this->assertSame('level2', $data['enums']['level']['value']);
    }

    public function test_validate_with_multiple_errors(): void
    {
        $signature = [
            'test',
            '::level->[a,b,c]=*',
            '::mode->[x,y,z]=*',
        ];
        $query = ['test', 'invalid1', 'invalid2'];

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $errors = $result->errors->toArray();
        $this->assertCount(2, $errors);
        $this->assertStringContainsString("Invalid value 'invalid1' for enum 'level'", $errors[0]);
        $this->assertStringContainsString("Invalid value 'invalid2' for enum 'mode'", $errors[1]);
    }

    public function test_parse_with_no_enums_returns_empty_data(): void
    {
        $signature = ['greet', '{name}'];
        $query = ['greet', 'John'];

        $result = $this->parser->parse($signature, $query);

        $data = $result->data->toArray();
        $this->assertArrayHasKey('enums', $data);
        $this->assertEmpty($data['enums']);  // ✅ Vérifier que enum est vide
        $this->assertSame($signature, $result->signature->toArray());
        $this->assertSame($query, $result->query->toArray());
    }

    public function test_validate_returns_valid_with_no_enums(): void
    {
        $signature = ['greet', '{name}'];
        $query = ['greet', 'John'];

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }
}
