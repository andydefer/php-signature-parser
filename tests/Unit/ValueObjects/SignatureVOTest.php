<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\ValueObjects;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SignatureVOTest extends TestCase
{
    // ==================== SOURCE TESTS ====================

    public function test_get_source(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertSame('docker', $vo->getSource());
    }

    // ==================== REQUIRED ARGUMENTS TESTS ====================

    public function test_get_required(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertSame('run', $vo->getRequired('container'));
        $this->assertNull($vo->getRequired('nonexistent'));
    }

    public function test_get_requireds(): void
    {
        $vo = new SignatureVO(
            'docker {container} {image} {--detach}',
            'docker run nginx --detach'
        );

        $this->assertEquals(
            ['container' => 'run', 'image' => 'nginx'],
            $vo->getRequireds()
        );
    }

    public function test_has_required(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertTrue($vo->hasRequired('container'));
        $this->assertFalse($vo->hasRequired('nonexistent'));
    }

    // ==================== DEFAULT ARGUMENTS TESTS ====================

    public function test_get_default(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip}',
            'backup /var/www tar.gz'
        );

        $this->assertSame('tar.gz', $vo->getDefault('format'));
        $this->assertNull($vo->getDefault('nonexistent'));
    }

    public function test_get_default_with_default_value(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip}',
            'backup /var/www'
        );

        $this->assertSame('zip', $vo->getDefault('format'));
    }

    public function test_get_defaults(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip} {output=dist}',
            'backup /var/www tar.gz'
        );

        $this->assertEquals(
            ['format' => 'tar.gz', 'output' => 'dist'],
            $vo->getDefaults()
        );
    }

    public function test_has_default(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip}',
            'backup /var/www'
        );

        $this->assertTrue($vo->hasDefault('format'));
        $this->assertFalse($vo->hasDefault('nonexistent'));
    }

    // ==================== NULLABLE ARGUMENTS TESTS ====================

    public function test_get_nullable(): void
    {
        $vo = new SignatureVO(
            'deploy {env?} {port?}',
            'deploy staging 8080'
        );

        $this->assertSame('staging', $vo->getNullable('env'));
        $this->assertSame('8080', $vo->getNullable('port'));
        $this->assertNull($vo->getNullable('nonexistent'));
    }

    public function test_get_nullable_missing_values(): void
    {
        $vo = new SignatureVO(
            'deploy {env?} {port?}',
            'deploy'
        );

        $this->assertNull($vo->getNullable('env'));
        $this->assertNull($vo->getNullable('port'));
    }

    public function test_get_nullables(): void
    {
        $vo = new SignatureVO(
            'deploy {env?} {port?}',
            'deploy staging 8080'
        );

        $this->assertEquals(
            ['env' => 'staging', 'port' => '8080'],
            $vo->getNullables()
        );
    }

    public function test_has_nullable(): void
    {
        $vo = new SignatureVO(
            'deploy {env?}',
            'deploy staging'
        );

        $this->assertTrue($vo->hasNullable('env'));
        $this->assertFalse($vo->hasNullable('nonexistent'));
    }

    // ==================== VARIADIC ARGUMENTS TESTS ====================

    public function test_get_variadic(): void
    {
        $vo = new SignatureVO(
            'backup {source} {excludes*}',
            'backup /var/www [cache, logs, tmp]'
        );

        $this->assertEquals(
            ['cache', 'logs', 'tmp'],
            $vo->getVariadic('excludes')
        );
        $this->assertEquals([], $vo->getVariadic('nonexistent'));
    }

    public function test_get_variadic_with_multiple(): void
    {
        $vo = new SignatureVO(
            'backup {excludes*} {includes*}',
            'backup [cache, logs, tmp] [src, vendor]'
        );

        $this->assertEquals(
            ['cache', 'logs', 'tmp'],
            $vo->getVariadic('excludes')
        );
        $this->assertEquals(
            ['src', 'vendor'],
            $vo->getVariadic('includes')
        );
    }

    public function test_get_variadics(): void
    {
        $vo = new SignatureVO(
            'backup {excludes*} {includes*}',
            'backup [cache, logs] [src]'
        );

        $this->assertEquals(
            [
                'excludes' => ['cache', 'logs'],
                'includes' => ['src'],
            ],
            $vo->getVariadics()
        );
    }

    public function test_has_variadic(): void
    {
        $vo = new SignatureVO(
            'backup {source} {excludes*}',
            'backup /var/www [cache, logs]'
        );

        $this->assertTrue($vo->hasVariadic('excludes'));
        $this->assertFalse($vo->hasVariadic('nonexistent'));
    }

    // ==================== FLAGS TESTS ====================

    public function test_get_flag(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach} {--rm}',
            'docker run --detach'
        );

        $this->assertTrue($vo->getFlag('detach'));
        $this->assertFalse($vo->getFlag('rm'));
        $this->assertFalse($vo->getFlag('nonexistent'));
    }

    public function test_get_flags(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach} {--rm}',
            'docker run --detach'
        );

        $this->assertEquals(
            ['detach' => true, 'rm' => false],
            $vo->getFlags()
        );
    }

    public function test_has_flag(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertTrue($vo->hasFlag('detach'));
        $this->assertFalse($vo->hasFlag('rm'));
    }

    // ==================== PARSED STRUCTURE TESTS ====================

    public function test_get_parsed(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $parsed = $vo->getParsed();

        $this->assertInstanceOf(StrictDataObject::class, $parsed);
        $this->assertEquals('docker', $parsed->source);
        $this->assertEquals(['container' => 'run'], $parsed->required->toArray());
        $this->assertEquals(['detach' => true], $parsed->flags->toArray());
    }

    public function test_get_value(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $value = $vo->getValue();

        $this->assertInstanceOf(StrictDataObject::class, $value);
        $this->assertEquals('docker', $value->source);
        $this->assertEquals(['container' => 'run'], $value->required->toArray());
        $this->assertEquals(['detach' => true], $value->flags->toArray());
    }

    // ==================== EQUALITY TESTS ====================

    public function test_equals(): void
    {
        $vo1 = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $vo2 = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $vo3 = new SignatureVO(
            'docker {container}',
            'docker run'
        );

        $this->assertTrue($vo1->equals($vo2));
        $this->assertFalse($vo1->equals($vo3));
    }

    // ==================== EXCEPTION TESTS ====================

    public function test_throws_exception_for_empty_signature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature cannot be empty');

        new SignatureVO('', 'docker run');
    }

    public function test_throws_exception_for_empty_query(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query cannot be empty');

        new SignatureVO('docker {container}', '');
    }

    // ==================== COMPLEX COMMAND TESTS ====================

    public function test_complex_command(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
        $query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('backup', $vo->getSource());
        $this->assertSame('/var/www', $vo->getRequired('source'));
        $this->assertSame('/backup', $vo->getRequired('destination'));
        $this->assertSame('tar.gz', $vo->getDefault('format'));
        $this->assertSame('dist', $vo->getDefault('output'));
        $this->assertEquals(['cache', 'logs', 'tmp'], $vo->getVariadic('excludes'));
        $this->assertEquals(['home', 'data', 'models'], $vo->getVariadic('purpose'));
        $this->assertTrue($vo->getFlag('force'));
        $this->assertFalse($vo->getFlag('verbose'));
    }

    public function test_docker_command(): void
    {
        $signature = 'docker {container} {--detach} {--rm}';
        $query = 'docker run --detach --rm';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('docker', $vo->getSource());
        $this->assertSame('run', $vo->getRequired('container'));
        $this->assertTrue($vo->getFlag('detach'));
        $this->assertTrue($vo->getFlag('rm'));
    }

    public function test_git_command(): void
    {
        $signature = 'git {command} {--all} {--force}';
        $query = 'git add --all';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('git', $vo->getSource());
        $this->assertSame('add', $vo->getRequired('command'));
        $this->assertTrue($vo->getFlag('all'));
        $this->assertFalse($vo->getFlag('force'));
    }

    public function test_command_with_default_value_only(): void
    {
        $signature = 'deploy {env=production}';
        $query = 'deploy';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('deploy', $vo->getSource());
        $this->assertSame('production', $vo->getDefault('env'));
    }

    public function test_command_with_default_value_overridden(): void
    {
        $signature = 'deploy {env=production}';
        $query = 'deploy staging';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('deploy', $vo->getSource());
        $this->assertSame('staging', $vo->getDefault('env'));
    }

    public function test_command_with_nullable_arguments(): void
    {
        $signature = 'deploy {env?} {port?} {--force}';
        $query = 'deploy staging --force';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('deploy', $vo->getSource());
        $this->assertSame('staging', $vo->getNullable('env'));
        $this->assertNull($vo->getNullable('port'));
        $this->assertTrue($vo->getFlag('force'));
    }

    // ==================== VALIDATION TESTS ====================

    public function test_is_valid_returns_true_for_valid_query(): void
    {
        $vo = new SignatureVO(
            'backup {source} {destination} {--force}',
            'backup /var/www /backup --force'
        );

        $this->assertTrue($vo->isValid());
        $this->assertCount(0, $vo->getValidationErrors());
    }

    public function test_is_valid_returns_false_for_invalid_query(): void
    {
        $vo = new SignatureVO(
            'backup {source} {destination} {--force}',
            'backup /var/www --force'
        );

        $this->assertFalse($vo->isValid());
        $this->assertCount(1, $vo->getValidationErrors());
    }

    public function test_get_validation_errors_returns_collection(): void
    {
        $vo = new SignatureVO(
            'backup {source} {destination} {--force}',
            'backup /var/www --force'
        );

        $errors = $vo->getValidationErrors();

        $this->assertInstanceOf(StringTypedCollection::class, $errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('destination', $errors->first());
    }

    public function test_get_validation_suggestions_returns_collection(): void
    {
        $vo = new SignatureVO(
            'backup {source} {destination} {--force}',
            'backup /var/www --force'
        );

        $suggestions = $vo->getValidationSuggestions();

        $this->assertInstanceOf(StringTypedCollection::class, $suggestions);
        $this->assertCount(1, $suggestions);
        $this->assertStringContainsString('Provide', $suggestions->first());
    }

    public function test_get_validation_result_returns_record(): void
    {
        $vo = new SignatureVO(
            'backup {source} {destination} {--force}',
            'backup /var/www /backup --force'
        );

        $result = $vo->getValidationResult();

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_is_valid_returns_false_for_unknown_flag(): void
    {
        $vo = new SignatureVO(
            'backup {source} {--force}',
            'backup /var/www --unknown'
        );

        $this->assertFalse($vo->isValid());
        $this->assertCount(1, $vo->getValidationErrors());
        $this->assertStringContainsString('unknown', $vo->getValidationErrors()->first());
    }

    public function test_is_valid_returns_true_for_nullable_missing_value(): void
    {
        $vo = new SignatureVO(
            'deploy {env?} {--force}',
            'deploy --force'
        );

        $this->assertTrue($vo->isValid());
        $this->assertCount(0, $vo->getValidationErrors());
        $this->assertNull($vo->getNullable('env'));
    }

    public function test_is_valid_returns_true_for_default_value(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip}',
            'backup /var/www'
        );

        $this->assertTrue($vo->isValid());
        $this->assertSame('zip', $vo->getDefault('format'));
    }

    public function test_is_valid_returns_true_for_variadic_arguments(): void
    {
        $vo = new SignatureVO(
            'process {files*} {--verbose}',
            'process [file1, file2] --verbose'
        );

        $this->assertTrue($vo->isValid());
        $this->assertEquals(['file1', 'file2'], $vo->getVariadic('files'));
    }

    public function test_is_valid_returns_false_for_variadic_without_signature(): void
    {
        $vo = new SignatureVO(
            'process {--verbose}',
            'process [file1, file2] --verbose'
        );

        $this->assertFalse($vo->isValid());
        $this->assertCount(1, $vo->getValidationErrors());
        $this->assertStringContainsString('Variadic', $vo->getValidationErrors()->first());
    }

    public function test_is_valid_returns_false_for_duplicate_flags(): void
    {
        $vo = new SignatureVO(
            'backup {--force}',
            'backup --force --force'
        );

        $this->assertFalse($vo->isValid());
        $this->assertCount(1, $vo->getValidationErrors());
        $this->assertStringContainsString('Duplicate', $vo->getValidationErrors()->first());
    }
}
