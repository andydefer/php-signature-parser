<?php

// tests/Unit/SignatureParserTest.php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\SignatureParser\SignatureParser;
use PHPUnit\Framework\TestCase;

final class SignatureParserTest extends TestCase
{
    public function test_parses_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
        $query = 'backup /var/www /backup tar.gz  [cache, logs, tmp] [home, data, models] --force';

        $parser = new SignatureParser;

        $result = $parser->parse($signature, $query);

        $this->assertEquals('backup', $result['source']);
        $this->assertEquals('/var/www', $result['required']['source']);
        $this->assertEquals('/backup', $result['required']['destination']);
        $this->assertEquals('tar.gz', $result['default']['format']);
        $this->assertEquals('dist', $result['default']['output']);
        $this->assertEquals(['cache', 'logs', 'tmp'], $result['variadic']['excludes']);
        $this->assertEquals(['home', 'data', 'models'], $result['variadic']['purpose']);
        $this->assertTrue($result['options']['force']);
        $this->assertFalse($result['options']['verbose']);
    }
}
