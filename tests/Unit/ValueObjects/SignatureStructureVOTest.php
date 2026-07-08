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
        $vo = new SignatureStructureVO('backup {source} {destination}');
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

    public function test_has_default(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo->hasDefault('format'));
        $this->assertFalse($vo->hasDefault('nonexistent'));
    }

    public function test_has_defaults(): void
    {
        $vo1 = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo1->hasDefaults());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasDefaults());
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

    // ==================== VALUE STRUCTURE TESTS ====================

    public function test_get_value(): void
    {
        $vo = new SignatureStructureVO('backup {source} {format=zip} {excludes*} {--force}');

        $value = $vo->getValue();

        $this->assertInstanceOf(StrictDataObject::class, $value);
        $this->assertEquals('backup', $value->source);
        $this->assertEquals(['source'], $value->required);
        $this->assertEquals(['format' => 'zip'], $value->default->toArray());
        $this->assertEquals(['excludes'], $value->variadic);
        $this->assertEquals(['force'], $value->flags);
    }

    // ==================== RAW & EQUALITY TESTS ====================

    public function test_get_raw(): void
    {
        $signature = 'backup {source} {format=zip}';
        $vo = new SignatureStructureVO($signature);
        $this->assertSame($signature, $vo->getRaw());
    }

    public function test_equals(): void
    {
        $vo1 = new SignatureStructureVO('backup {source}');
        $vo2 = new SignatureStructureVO('backup {source}');
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
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
    }

    public function test_signature_with_all_types(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}');

        $this->assertSame('backup', $vo->getSource());
        $this->assertEquals(['source', 'destination'], $vo->getRequireds());
        $this->assertEquals(['format' => 'zip', 'output' => 'dist'], $vo->getDefaults());
        $this->assertEquals(['excludes', 'purpose'], $vo->getVariadics());
        $this->assertEquals(['force', 'verbose'], $vo->getFlags());
        $this->assertTrue($vo->hasRequireds());
        $this->assertTrue($vo->hasDefaults());
        $this->assertTrue($vo->hasVariadics());
        $this->assertTrue($vo->hasFlags());
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

    public function test_signature_with_nullable_syntax(): void
    {
        $vo = new SignatureStructureVO('deploy {env=?} {port=?}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
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
        $vo = new SignatureStructureVO('deploy {env=production} {port=?} {--force}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEquals(['env' => 'production'], $vo->getDefaults());
        $this->assertEquals(['force'], $vo->getFlags());
    }
    // ==================== VALIDATION TESTS ====================

    public function test_is_valid_returns_true_for_valid_signature(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {--force}');

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

    public function test_validation_detects_invalid_order(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip} {source} {--force}');

        $this->assertFalse($vo->isValid());
        $errors = $vo->getValidationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Required argument', $errors[0]);
    }

    public function test_validation_passes_with_nullable_arguments(): void
    {
        $vo = new SignatureStructureVO('deploy {env=?} {port=?} {--force}');

        $this->assertTrue($vo->isValid());
        $this->assertEmpty($vo->getValidationErrors());
    }

    public function test_validation_passes_with_complex_signature(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}');

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
}
