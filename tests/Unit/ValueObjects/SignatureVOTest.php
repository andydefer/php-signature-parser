<?php

// tests/Unit/ValueObjects/SignatureVOTest.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\ValueObjects;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\ValueObjects\SignatureVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SignatureVOTest extends TestCase
{
    public function test_get_source(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertSame('docker', $vo->getSource());
    }

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

    public function test_get_option(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach} {--rm}',
            'docker run --detach'
        );

        $this->assertTrue($vo->getOption('detach'));
        $this->assertFalse($vo->getOption('rm'));
        $this->assertFalse($vo->getOption('nonexistent'));
    }

    public function test_get_options(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach} {--rm}',
            'docker run --detach'
        );

        $this->assertEquals(
            ['detach' => true, 'rm' => false],
            $vo->getOptions()
        );
    }

    public function test_has_option(): void
    {
        $vo = new SignatureVO(
            'docker {container} {--detach}',
            'docker run --detach'
        );

        $this->assertTrue($vo->hasOption('detach'));
        $this->assertFalse($vo->hasOption('rm'));
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

    public function test_has_default(): void
    {
        $vo = new SignatureVO(
            'backup {source} {format=zip}',
            'backup /var/www'
        );

        $this->assertTrue($vo->hasDefault('format'));
        $this->assertFalse($vo->hasDefault('nonexistent'));
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
        $this->assertEquals(['detach' => true], $parsed->options->toArray());
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
        $this->assertEquals(['detach' => true], $value->options->toArray());
    }

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
        $this->assertTrue($vo->getOption('force'));
        $this->assertFalse($vo->getOption('verbose'));
    }

    public function test_docker_command(): void
    {
        $signature = 'docker {container} {--detach} {--rm}';
        $query = 'docker run --detach --rm';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('docker', $vo->getSource());
        $this->assertSame('run', $vo->getRequired('container'));
        $this->assertTrue($vo->getOption('detach'));
        $this->assertTrue($vo->getOption('rm'));
    }

    public function test_git_command(): void
    {
        $signature = 'git {command} {--all} {--force}';
        $query = 'git add --all';

        $vo = new SignatureVO($signature, $query);

        $this->assertSame('git', $vo->getSource());
        $this->assertSame('add', $vo->getRequired('command'));
        $this->assertTrue($vo->getOption('all'));
        $this->assertFalse($vo->getOption('force'));
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
}
