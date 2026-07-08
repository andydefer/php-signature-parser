<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\Tests\Fixtures\CustomParser;
use PHPUnit\Framework\TestCase;

final class SignatureParserTest extends TestCase
{
    private SignatureParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SignatureParser;
    }

    // ==================== PARSE TESTS ====================

    public function test_parses_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';
        $query = 'backup /var/www /backup tar.gz [cache, logs, tmp] [home, data, models] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame('dist', $result->default->last()->value);
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadic->first()->values->toArray());
        $this->assertSame(['home', 'data', 'models'], $result->variadic->last()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_replaces_caret_with_space_in_required_arguments(): void
    {
        $signature = 'user:create {name} {email}';
        $query = 'user:create John^Doe john@example.com';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John Doe', $result->required->first()->value);
        $this->assertSame('john@example.com', $result->required->last()->value);
    }

    public function test_replaces_caret_with_space_in_default_arguments(): void
    {
        $signature = 'user:list {format=zip} {output=dist}';
        $query = 'user:list tar^gz build^folder';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('tar gz', $result->default->first()->value);
        $this->assertSame('build folder', $result->default->last()->value);
    }

    public function test_replaces_caret_with_space_in_variadic_arguments(): void
    {
        $signature = 'process {files*}';
        $query = 'process [file^1.txt, file^2.txt, my^file^3.txt]';

        $result = $this->parser->parse($signature, $query);

        $values = $result->variadic->first()->values->toArray();
        $this->assertSame(['file 1.txt', 'file 2.txt', 'my file 3.txt'], $values);
    }

    public function test_replaces_caret_in_mixed_arguments(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
        $query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/home/user/My Project', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertSame('tar gz', $result->default->first()->value);
        $this->assertSame(['cache folder', 'logs folder'], $result->variadic->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_keeps_text_without_caret_unchanged(): void
    {
        $signature = 'user:create {name} {email}';
        $query = 'user:create John john@example.com';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John', $result->required->first()->value);
        $this->assertSame('john@example.com', $result->required->last()->value);
    }

    public function test_replaces_multiple_carets_in_same_value(): void
    {
        $signature = 'user:create {name}';
        $query = 'user:create John^Michael^Doe';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John Michael Doe', $result->required->first()->value);
    }

    public function test_extracts_signature_elements(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';

        $result = $this->parser->extractSignatureElements($signature);

        $this->assertSame(['backup', 'source', 'destination', 'format=zip', '--force'], $result->toArray());
    }

    public function test_extracts_query_elements(): void
    {
        $query = 'backup /var/www /backup tar.gz [cache, logs] --force';

        $result = $this->parser->extractQueryElements($query);

        $this->assertSame(['backup', '/var/www', '/backup', 'tar.gz', '[cache, logs]', '--force'], $result->toArray());
    }

    public function test_parses_without_optional_elements(): void
    {
        $signature = 'backup {source} {destination}';
        $query = 'backup /var/www /backup';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('/backup', $result->required->last()->value);
        $this->assertCount(0, $result->default);
        $this->assertCount(0, $result->nullable);
        $this->assertCount(0, $result->variadic);
        $this->assertCount(0, $result->flags);
    }

    public function test_parses_with_only_default_values(): void
    {
        $signature = 'backup {format=zip} {output=dist}';
        $query = 'backup tar.gz';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame('dist', $result->default->last()->value);
    }

    public function test_parses_with_only_nullable_arguments(): void
    {
        $signature = 'deploy {env?} {port?}';
        $query = 'deploy staging 8080';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('staging', $result->nullable->first()->value);
        $this->assertSame('8080', $result->nullable->last()->value);
    }

    public function test_parses_with_nullable_arguments_missing_values(): void
    {
        $signature = 'deploy {env?} {port?}';
        $query = 'deploy';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertNull($result->nullable->first()->value);
        $this->assertNull($result->nullable->last()->value);
    }

    public function test_parses_with_only_flags(): void
    {
        $signature = 'backup {--force} {--verbose}';
        $query = 'backup --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_parses_with_variadic_only(): void
    {
        $signature = 'backup {excludes*}';
        $query = 'backup [cache, logs, tmp]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadic->first()->values->toArray());
    }

    public function test_handles_empty_query(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $query = 'backup';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('', $result->required->first()->value);
        $this->assertSame('', $result->required->last()->value);
        $this->assertSame('zip', $result->default->first()->value);
        $this->assertFalse($result->flags->first()->value);
    }

    public function test_removes_and_adds_parsers(): void
    {
        $initialCount = count($this->parser->getParsers());

        $this->parser->removeParser('AndyDefer\SignatureParser\Parsers\FlagParser');
        $this->assertCount($initialCount - 1, $this->parser->getParsers());

        $this->parser->addParser(new CustomParser);
        $this->assertCount($initialCount, $this->parser->getParsers());
    }

    // ==================== VALIDATION TESTS ====================

    public function test_validation_passes_for_valid_query(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $query = 'backup /var/www /backup tar.gz --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_validation_passes_for_nullable_argument_without_value(): void
    {
        $signature = 'deploy {env?} {--force}';
        $query = 'deploy --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_missing_required_argument(): void
    {
        $signature = 'backup {source} {destination} {--force}';
        $query = 'backup /var/www --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('destination', $result->errors->first());
    }

    public function test_validation_fails_for_unknown_flag(): void
    {
        $signature = 'backup {source} {--force}';
        $query = 'backup /var/www --unknown';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('unknown', $result->errors->first());
    }

    public function test_validation_fails_for_missing_source(): void
    {
        $signature = 'backup {source}';
        $query = '';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
    }

    public function test_is_valid_returns_boolean(): void
    {
        $signature = 'backup {source} {--force}';
        $validQuery = 'backup /var/www --force';
        $invalidQuery = 'backup --force';

        $this->assertTrue($this->parser->isValid($signature, $validQuery));
        $this->assertFalse($this->parser->isValid($signature, $invalidQuery));
    }

    public function test_get_validation_errors_returns_collection(): void
    {
        $signature = 'backup {source} {destination} {--force}';
        $query = 'backup /var/www --force';

        $errors = $this->parser->getValidationErrors($signature, $query);

        $this->assertInstanceOf(StringTypedCollection::class, $errors);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('destination', $errors->first());
    }

    public function test_validation_handles_variadic_arguments(): void
    {
        $signature = 'process {files*} {--verbose}';
        $query = 'process [file1, file2] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_variadic_without_signature(): void
    {
        $signature = 'process {--verbose}';
        $query = 'process [file1, file2] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Variadic', $result->errors->first());
    }

    public function test_validation_detects_duplicate_flags(): void
    {
        $signature = 'backup {--force} {--verbose}';
        $query = 'backup --force --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Duplicate', $result->errors->first());
    }

    public function test_validation_handles_default_values(): void
    {
        $signature = 'backup {source} {format=zip} {--force}';
        $query = 'backup /var/www --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_handles_nullable_with_default(): void
    {
        $signature = 'deploy {env?} {port=8080} {--force}';
        $query = 'deploy --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_validation_fails_for_empty_default_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot have empty value');

        $signature = 'deploy {env=} {--force}';
        $query = 'deploy --force';

        $this->parser->parse($signature, $query);
    }

    public function test_validation_combines_errors_from_all_parsers(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $query = 'backup --unknown --another';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(2, count($result->errors));
    }

    public function test_validation_provides_suggestions(): void
    {
        $signature = 'backup {source} {destination} {--force}';
        $query = 'backup /var/www --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertCount(1, $result->suggestions);
        $this->assertStringContainsString('Provide', $result->suggestions->first());
    }

    public function test_nullable_with_quotes_returns_quotes(): void
    {
        $signature = 'deploy {env?}';
        $query = 'deploy ""';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('""', $result->nullable->first()->value);
    }

    public function test_nullable_with_missing_value_returns_null(): void
    {
        $signature = 'deploy {env?}';
        $query = 'deploy';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertNull($result->nullable->first()->value);
    }
}
