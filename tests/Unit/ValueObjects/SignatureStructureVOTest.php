<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\ValueObjects;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\Records\ValidationResultRecord;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SignatureStructureVOTest extends TestCase
{
    // ==================== SOURCE TESTS ====================

    public function test_get_source(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {limit=?}');
        $this->assertSame('backup', $vo->getSource());
    }

    // ==================== REQUIRED ARGUMENTS TESTS ====================

    public function test_get_requireds(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination}');
        $this->assertEquals(['source', 'destination'], $vo->getRequireds());
    }

    public function test_has_required(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination}');
        $this->assertTrue($vo->hasRequired('source'));
        $this->assertFalse($vo->hasRequired('nonexistent'));
    }

    public function test_has_requireds(): void
    {
        $vo1 = new SignatureStructureVO('backup {source}');
        $this->assertTrue($vo1->hasRequireds());

        $vo2 = new SignatureStructureVO('backup {format=zip}');
        $this->assertFalse($vo2->hasRequireds());
    }

    // ==================== DEFAULT ARGUMENTS TESTS ====================

    public function test_get_defaults(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip} {output=dist}');
        $this->assertEquals(['format' => 'zip', 'output' => 'dist'], $vo->getDefaults());
    }

    public function test_get_defaults_in_nullable_format(): void
    {
        $vo = new SignatureStructureVO('backup {format=?} {output=?}');
        $this->assertEquals(['format' => null, 'output' => null], $vo->getDefaults());
    }

    public function test_has_default(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo->hasDefault('format'));
        $this->assertFalse($vo->hasDefault('nonexistent'));
    }

    public function test_has_default_for_nullable(): void
    {
        $vo = new SignatureStructureVO('backup {format=?}');
        $this->assertTrue($vo->hasDefault('format'));
    }

    public function test_has_defaults(): void
    {
        $vo1 = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo1->hasDefaults());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasDefaults());

        $vo3 = new SignatureStructureVO('backup {format=?}');
        $this->assertTrue($vo3->hasDefaults());
    }

    public function test_get_defaults_mixed(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip} {output=?} {compression=?} {algorithm=gzip}');
        $defaults = $vo->getDefaults();

        $this->assertEquals([
            'format' => 'zip',
            'output' => null,
            'compression' => null,
            'algorithm' => 'gzip',
        ], $defaults);
    }

    // ==================== VARIADIC ARGUMENTS TESTS ====================

    public function test_get_variadics(): void
    {
        $vo = new SignatureStructureVO('backup {excludes*} {purpose*}');
        $this->assertEquals(['excludes', 'purpose'], $vo->getVariadics());
    }

    public function test_has_variadic(): void
    {
        $vo = new SignatureStructureVO('backup {excludes*}');
        $this->assertTrue($vo->hasVariadic('excludes'));
        $this->assertFalse($vo->hasVariadic('nonexistent'));
    }

    public function test_has_variadics(): void
    {
        $vo1 = new SignatureStructureVO('backup {excludes*}');
        $this->assertTrue($vo1->hasVariadics());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasVariadics());
    }

    // ==================== FLAGS TESTS ====================

    public function test_get_flags(): void
    {
        $vo = new SignatureStructureVO('backup {--force} {--verbose}');
        $this->assertEquals(['force', 'verbose'], $vo->getFlags());
    }

    public function test_has_flag(): void
    {
        $vo = new SignatureStructureVO('backup {--force}');
        $this->assertTrue($vo->hasFlag('force'));
        $this->assertFalse($vo->hasFlag('nonexistent'));
    }

    public function test_has_flags(): void
    {
        $vo1 = new SignatureStructureVO('backup {--force}');
        $this->assertTrue($vo1->hasFlags());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasFlags());
    }

    // ==================== ENUM TESTS ====================

    public function test_get_enums(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle ::mode->[dev,staging,prod]=dev');
        $enums = $vo->getEnums();

        $this->assertCount(2, $enums);
        $this->assertArrayHasKey('level', $enums);
        $this->assertArrayHasKey('mode', $enums);
        $this->assertEquals(['beginner', 'middle', 'master'], $enums['level']['allowed_values']);
        $this->assertEquals('middle', $enums['level']['default_value']);
        $this->assertFalse($enums['level']['is_required']);
        $this->assertFalse($enums['level']['is_optional']);
        $this->assertEquals(['dev', 'staging', 'prod'], $enums['mode']['allowed_values']);
        $this->assertEquals('dev', $enums['mode']['default_value']);
    }

    public function test_has_enum(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertTrue($vo->hasEnum('level'));
        $this->assertFalse($vo->hasEnum('nonexistent'));
    }

    public function test_has_enums(): void
    {
        $vo1 = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertTrue($vo1->hasEnums());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasEnums());
    }

    public function test_get_enum_allowed_values(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $allowed = $vo->getEnumAllowedValues('level');

        $this->assertNotNull($allowed);
        $this->assertEquals(['beginner', 'middle', 'master'], $allowed);
        $this->assertNull($vo->getEnumAllowedValues('nonexistent'));
    }

    public function test_get_enum_default_value(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertEquals('middle', $vo->getEnumDefaultValue('level'));
        $this->assertNull($vo->getEnumDefaultValue('nonexistent'));
    }

    public function test_is_enum_required(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=*');
        $this->assertTrue($vo->isEnumRequired('level'));

        $vo2 = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertFalse($vo2->isEnumRequired('level'));
    }

    public function test_is_enum_optional(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=?');
        $this->assertTrue($vo->isEnumOptional('level'));

        $vo2 = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertFalse($vo2->isEnumOptional('level'));
    }

    public function test_enum_with_required_default(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=*');
        $enums = $vo->getEnums();

        $this->assertTrue($enums['level']['is_required']);
        $this->assertFalse($enums['level']['is_optional']);
        $this->assertNull($enums['level']['default_value']);
    }

    public function test_enum_with_optional_default(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=?');
        $enums = $vo->getEnums();

        $this->assertFalse($enums['level']['is_required']);
        $this->assertTrue($enums['level']['is_optional']);
        $this->assertNull($enums['level']['default_value']);
    }

    public function test_enum_with_empty_allowed_values(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[]=*');
        $enums = $vo->getEnums();

        $this->assertArrayHasKey('level', $enums);
        $this->assertEmpty($enums['level']['allowed_values']);
        $this->assertTrue($enums['level']['is_required']);
    }

    public function test_enum_with_single_allowed_value(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[only]=only');
        $enums = $vo->getEnums();

        $this->assertEquals(['only'], $enums['level']['allowed_values']);
        $this->assertEquals('only', $enums['level']['default_value']);
    }

    public function test_enum_with_underscores_in_name(): void
    {
        $vo = new SignatureStructureVO('set-level ::user_level->[beginner,middle,master]=middle');
        $enums = $vo->getEnums();

        $this->assertArrayHasKey('user_level', $enums);
        $this->assertEquals(['beginner', 'middle', 'master'], $enums['user_level']['allowed_values']);
    }

    // ==================== VALUE STRUCTURE TESTS ====================

    public function test_get_value(): void
    {
        $vo = new SignatureStructureVO('backup ::level->[beginner,middle,master]=middle {source} {format=zip} {output=?} {excludes*} {--force}');

        $value = $vo->getValue();

        $this->assertInstanceOf(StrictDataObject::class, $value);
        $this->assertEquals('backup', $value->source);
        $this->assertEquals(['source'], $value->required);
        $this->assertEquals(['format' => 'zip', 'output' => null], $value->default->toArray());
        $this->assertEquals(['excludes'], $value->variadic);
        $this->assertEquals(['force'], $value->flags);
        $this->assertArrayHasKey('level', $value->enums);
        $this->assertEquals(['beginner', 'middle', 'master'], $value->enums['level']['allowed_values']);
    }

    // ==================== RAW & EQUALITY TESTS ====================

    public function test_get_raw(): void
    {
        $signature = 'backup ::level->[beginner,middle,master]=middle {source} {format=zip}';
        $vo = new SignatureStructureVO($signature);
        $this->assertSame($signature, $vo->getRaw());
    }

    public function test_equals(): void
    {
        $vo1 = new SignatureStructureVO('backup ::level->[beginner,middle,master]=middle {source}');
        $vo2 = new SignatureStructureVO('backup ::level->[beginner,middle,master]=middle {source}');
        $vo3 = new SignatureStructureVO('backup {destination}');

        $this->assertTrue($vo1->equals($vo2));
        $this->assertFalse($vo1->equals($vo3));
    }

    // ==================== EXCEPTION TESTS ====================

    public function test_throws_exception_for_empty_signature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature cannot be empty');
        new SignatureStructureVO('');
    }

    // ==================== COMPLEX SIGNATURE TESTS ====================

    public function test_signature_with_only_source(): void
    {
        $vo = new SignatureStructureVO('backup');

        $this->assertSame('backup', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertEmpty($vo->getEnums());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
        $this->assertFalse($vo->hasEnums());
    }

    public function test_signature_with_all_types_in_correct_order(): void
    {
        $vo = new SignatureStructureVO(
            'backup ::level->[beginner,middle,master]=middle {source} {destination} {format=zip} {output=dist} {env=?} {excludes*} {purpose*} {--force} {--verbose}'
        );

        $this->assertSame('backup', $vo->getSource());
        $this->assertEquals(['source', 'destination'], $vo->getRequireds());
        $this->assertEquals(['format' => 'zip', 'output' => 'dist', 'env' => null], $vo->getDefaults());
        $this->assertEquals(['excludes', 'purpose'], $vo->getVariadics());
        $this->assertEquals(['force', 'verbose'], $vo->getFlags());
        $this->assertArrayHasKey('level', $vo->getEnums());
        $this->assertTrue($vo->hasRequireds());
        $this->assertTrue($vo->hasDefaults());
        $this->assertTrue($vo->hasVariadics());
        $this->assertTrue($vo->hasFlags());
        $this->assertTrue($vo->hasEnums());
    }

    public function test_signature_with_only_enums(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle ::mode->[dev,staging,prod]=dev');

        $this->assertSame('set-level', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertCount(2, $vo->getEnums());
        $this->assertTrue($vo->hasEnums());
        $this->assertTrue($vo->hasEnum('level'));
        $this->assertTrue($vo->hasEnum('mode'));
    }

    public function test_signature_with_only_flags(): void
    {
        $vo = new SignatureStructureVO('deploy {--force} {--verbose}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEquals(['force', 'verbose'], $vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertTrue($vo->hasFlags());
    }

    public function test_signature_with_only_defaults(): void
    {
        $vo = new SignatureStructureVO('deploy {env=production} {port=8080}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEquals(['env' => 'production', 'port' => '8080'], $vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertTrue($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
    }

    public function test_signature_with_only_nullables(): void
    {
        $vo = new SignatureStructureVO('deploy {env=?} {port=?}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEquals(['env' => null, 'port' => null], $vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertTrue($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
    }

    public function test_signature_with_only_variadics(): void
    {
        $vo = new SignatureStructureVO('process {files*}');

        $this->assertSame('process', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEquals(['files'], $vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertTrue($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
    }

    public function test_mixed_default_and_nullable(): void
    {
        $vo = new SignatureStructureVO('deploy {env=production} {port=?} {compression=6} {--force}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEquals(['env' => 'production', 'port' => null, 'compression' => '6'], $vo->getDefaults());
        $this->assertEquals(['force'], $vo->getFlags());
    }

    // ==================== HAS ARGUMENT TESTS ====================

    public function test_has_argument_for_required(): void
    {
        $vo = new SignatureStructureVO('backup {source}');
        $this->assertTrue($vo->hasArgument('source'));
        $this->assertFalse($vo->hasArgument('nonexistent'));
    }

    public function test_has_argument_for_default(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo->hasArgument('format'));
    }

    public function test_has_argument_for_nullable(): void
    {
        $vo = new SignatureStructureVO('backup {format=?}');
        $this->assertTrue($vo->hasArgument('format'));
    }

    public function test_has_argument_for_variadic(): void
    {
        $vo = new SignatureStructureVO('backup {files*}');
        $this->assertTrue($vo->hasArgument('files'));
    }

    public function test_has_argument_for_enum(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');
        $this->assertFalse($vo->hasArgument('level'));
    }

    // ==================== VALIDATION TESTS ====================

    public function test_is_valid_returns_true_for_valid_signature(): void
    {
        // ✅ Ordre correct : Source → Required → Default → Nullable → ENUM → Variadic → Flags
        $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {env=?} ::level->[beginner,middle,master]=middle {excludes*} {--force}');

        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
        $this->assertEmpty($vo->getValidationSuggestions());
    }

    public function test_is_valid_returns_false_for_invalid_signature(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip} {source} {--force}');

        $this->assertFalse($vo->isValid());
        $this->assertNotEmpty($vo->getValidationErrors());
    }

    public function test_get_validation_errors_returns_errors_for_invalid_signature(): void
    {
        $vo = new SignatureStructureVO('backup {source} {invalid!} {--force}');

        $errors = $vo->getValidationErrors();

        $this->assertNotEmpty($errors);
        $this->assertIsArray($errors);
        $this->assertStringContainsString('invalid!', $errors[0]);
    }

    public function test_get_validation_suggestions_returns_suggestions_for_invalid_signature(): void
    {
        $vo = new SignatureStructureVO('backup {source} {invalid!} {--force}');

        $suggestions = $vo->getValidationSuggestions();

        $this->assertNotEmpty($suggestions);
        $this->assertIsArray($suggestions);
    }

    public function test_get_validation_result_returns_validation_result_record(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {--force}');

        $result = $vo->getValidationResult();

        $this->assertInstanceOf(ValidationResultRecord::class, $result);
        $this->assertTrue($result->isValid);
    }

    public function test_validation_detects_duplicate_argument_names(): void
    {
        $vo = new SignatureStructureVO('backup {source} {source} {--force}');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate', strtolower($errors[0]));
    }

    public function test_validation_detects_invalid_token_syntax(): void
    {
        $vo = new SignatureStructureVO('backup {source} {??} {--force}');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('??', $errors[0]);
    }

    public function test_validation_detects_enum_after_flags(): void
    {
        // ❌ Enum après les flags (invalide)
        $vo = new SignatureStructureVO('backup {source} {destination} {--force} ::level->[beginner,middle,master]=middle');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot appear after flags', $errors[0]);
    }

    public function test_validation_detects_enum_after_variadic(): void
    {
        // ❌ Enum après variadic (invalide)
        $vo = new SignatureStructureVO('backup {source} {destination} {excludes*} ::level->[beginner,middle,master]=middle {--force}');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Enum argument', $errors[0]);
        $this->assertStringContainsString('must appear after default arguments and before variadic or flags', $errors[0]);
    }

    public function test_validation_passes_with_nullable_arguments(): void
    {
        $vo = new SignatureStructureVO('deploy {env=?} {port=?} {--force}');

        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_passes_with_enum_arguments(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle');

        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_passes_with_complex_signature(): void
    {
        // ✅ Ordre correct : Source → Required → Default → Nullable → ENUM → Variadic → Flags
        $vo = new SignatureStructureVO(
            'backup {source} {destination} {format=zip} {output=dist} {env=?} ::level->[beginner,middle,master]=middle {excludes*} {purpose*} {--force} {--verbose}'
        );

        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_fails_for_source_with_invalid_characters(): void
    {
        $vo = new SignatureStructureVO('backup! {source} {--force}');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('source', $errors[0]);
    }

    public function test_get_validation_errors_returns_empty_array_for_valid_signature(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {--force}');

        $this->assertEmpty($vo->getValidationErrors());
        $this->assertEmpty($vo->getValidationSuggestions());
    }

    // ==================== ORDER VALIDATION TESTS ====================

    public function test_validation_passes_with_nullable_before_variadic(): void
    {
        $vo = new SignatureStructureVO('backup {source} {format=zip} {output=?} {files*} {--force}');
        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_fails_with_required_after_nullable(): void
    {
        $vo = new SignatureStructureVO('backup {format=?} {source} {--force}');
        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Required argument', $errors[0]);
    }

    public function test_validation_fails_with_variadic_before_nullable(): void
    {
        $vo = new SignatureStructureVO('backup {source} {files*} {output=?} {--force}');
        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
    }

    public function test_validation_passes_with_nullables_only(): void
    {
        $vo = new SignatureStructureVO('deploy {env=?} {port=?}');
        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_passes_with_enums_first(): void
    {
        $vo = new SignatureStructureVO('set-level ::level->[beginner,middle,master]=middle ::mode->[dev,staging,prod]=dev');
        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }
}
