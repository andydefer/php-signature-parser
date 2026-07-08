<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\ValueObjects;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SignatureStructureVOTest extends TestCase
{
    public function test_get_source(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination}');
        $this->assertSame('backup', $vo->getSource());
    }

    public function test_get_requireds(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination}');
        $this->assertEquals(['source', 'destination'], $vo->getRequireds());
    }

    public function test_get_defaults(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip} {output=dist}');
        $this->assertEquals(['format' => 'zip', 'output' => 'dist'], $vo->getDefaults());
    }

    public function test_get_defaults_with_nullable(): void
    {
        $vo = new SignatureStructureVO('backup {format?}');
        $this->assertEmpty($vo->getDefaults());
    }

    public function test_get_defaults_with_empty_default(): void
    {
        $vo = new SignatureStructureVO('backup {format=}');
        $this->assertEmpty($vo->getDefaults());
    }

    public function test_get_variadics(): void
    {
        $vo = new SignatureStructureVO('backup {excludes*} {purpose*}');
        $this->assertEquals(['excludes', 'purpose'], $vo->getVariadics());
    }

    public function test_get_flags(): void
    {
        $vo = new SignatureStructureVO('backup {--force} {--verbose}');
        $this->assertEquals(['force', 'verbose'], $vo->getFlags());
    }

    public function test_has_required(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination}');
        $this->assertTrue($vo->hasRequired('source'));
        $this->assertFalse($vo->hasRequired('nonexistent'));
    }

    public function test_has_default(): void
    {
        $vo = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo->hasDefault('format'));
        $this->assertFalse($vo->hasDefault('nonexistent'));
    }

    public function test_has_variadic(): void
    {
        $vo = new SignatureStructureVO('backup {excludes*}');
        $this->assertTrue($vo->hasVariadic('excludes'));
        $this->assertFalse($vo->hasVariadic('nonexistent'));
    }

    public function test_has_flag(): void
    {
        $vo = new SignatureStructureVO('backup {--force}');
        $this->assertTrue($vo->hasFlag('force'));
        $this->assertFalse($vo->hasFlag('nonexistent'));
    }

    public function test_count_arguments(): void
    {
        $vo = new SignatureStructureVO('backup {source} {destination} {format=zip} {excludes*}');
        $this->assertEquals(4, $vo->countArguments());
    }

    public function test_has_requireds(): void
    {
        $vo1 = new SignatureStructureVO('backup {source}');
        $this->assertTrue($vo1->hasRequireds());

        $vo2 = new SignatureStructureVO('backup {format=zip}');
        $this->assertFalse($vo2->hasRequireds());
    }

    public function test_has_defaults(): void
    {
        $vo1 = new SignatureStructureVO('backup {format=zip}');
        $this->assertTrue($vo1->hasDefaults());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasDefaults());
    }

    public function test_has_variadics(): void
    {
        $vo1 = new SignatureStructureVO('backup {excludes*}');
        $this->assertTrue($vo1->hasVariadics());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasVariadics());
    }

    public function test_has_flags(): void
    {
        $vo1 = new SignatureStructureVO('backup {--force}');
        $this->assertTrue($vo1->hasFlags());

        $vo2 = new SignatureStructureVO('backup {source}');
        $this->assertFalse($vo2->hasFlags());
    }

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

    public function test_throws_exception_for_empty_signature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Signature cannot be empty');
        new SignatureStructureVO('');
    }

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
        $this->assertEquals(6, $vo->countArguments());
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
        $this->assertEquals(0, $vo->countArguments());
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
        $this->assertEquals(2, $vo->countArguments());
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
        $this->assertEquals(1, $vo->countArguments());
    }

    public function test_signature_with_nullable_arguments(): void
    {
        $vo = new SignatureStructureVO('deploy {env?} {port?}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
        $this->assertEquals(0, $vo->countArguments());
    }

    public function test_signature_with_empty_defaults(): void
    {
        $vo = new SignatureStructureVO('deploy {env=} {port=}');

        $this->assertSame('deploy', $vo->getSource());
        $this->assertEmpty($vo->getRequireds());
        $this->assertEmpty($vo->getDefaults());
        $this->assertEmpty($vo->getVariadics());
        $this->assertEmpty($vo->getFlags());
        $this->assertFalse($vo->hasRequireds());
        $this->assertFalse($vo->hasDefaults());
        $this->assertFalse($vo->hasVariadics());
        $this->assertFalse($vo->hasFlags());
        $this->assertEquals(0, $vo->countArguments());
    }
}
