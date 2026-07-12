<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\Parsers\EnumParser;
use AndyDefer\SignatureParser\SignatureParser;
use AndyDefer\SignatureParser\Tests\Fixtures\CustomParser;
use InvalidArgumentException;
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
        $query = 'backup /var/www /backup tar.gz dist [cache, logs, tmp] [home, data, models] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->requireds->first()->value);
        $this->assertSame('/backup', $result->requireds->last()->value);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertSame('dist', $result->defaults->last()->value);
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadics->first()->values->toArray());
        $this->assertSame(['home', 'data', 'models'], $result->variadics->last()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_replaces_caret_with_space_in_required_arguments(): void
    {
        $signature = 'user:create {name} {email}';
        $query = 'user:create John^Doe john@example.com';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John Doe', $result->requireds->first()->value);
        $this->assertSame('john@example.com', $result->requireds->last()->value);
    }

    public function test_replaces_caret_with_space_in_default_arguments(): void
    {
        $signature = 'user:list {format=zip} {output=dist}';
        $query = 'user:list tar^gz build^folder';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('tar gz', $result->defaults->first()->value);
        $this->assertSame('build folder', $result->defaults->last()->value);
    }

    public function test_replaces_caret_with_space_in_variadic_arguments(): void
    {
        $signature = 'process {files*}';
        $query = 'process [file^1.txt, file^2.txt, my^file^3.txt]';

        $result = $this->parser->parse($signature, $query);

        $values = $result->variadics->first()->values->toArray();
        $this->assertSame(['file 1.txt', 'file 2.txt', 'my file 3.txt'], $values);
    }

    public function test_replaces_caret_in_mixed_arguments(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';
        $query = 'backup /home/user/My^Project /backup tar^gz [cache^folder, logs^folder] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('/home/user/My Project', $result->requireds->first()->value);
        $this->assertSame('/backup', $result->requireds->last()->value);
        $this->assertSame('tar gz', $result->defaults->first()->value);
        $this->assertSame(['cache folder', 'logs folder'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_keeps_text_without_caret_unchanged(): void
    {
        $signature = 'user:create {name} {email}';
        $query = 'user:create John john@example.com';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertSame('john@example.com', $result->requireds->last()->value);
    }

    public function test_replaces_multiple_carets_in_same_value(): void
    {
        $signature = 'user:create {name}';
        $query = 'user:create John^Michael^Doe';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('John Michael Doe', $result->requireds->first()->value);
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
        $this->assertSame('/var/www', $result->requireds->first()->value);
        $this->assertSame('/backup', $result->requireds->last()->value);
        $this->assertCount(0, $result->defaults);
        $this->assertCount(0, $result->variadics);
        $this->assertCount(0, $result->flags);
    }

    public function test_parses_with_only_default_values(): void
    {
        $signature = 'backup {format=zip} {output=dist}';
        $query = 'backup tar.gz';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertSame('dist', $result->defaults->last()->value);
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
        $this->assertSame(['cache', 'logs', 'tmp'], $result->variadics->first()->values->toArray());
    }

    public function test_handles_empty_query(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {--force}';
        $query = 'backup';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('', $result->requireds->first()->value);
        $this->assertSame('', $result->requireds->last()->value);
        $this->assertSame('zip', $result->defaults->first()->value);
        $this->assertFalse($result->flags->first()->value);
    }

    public function test_parses_with_default_and_nullable_mixed(): void
    {
        $signature = 'deploy {port=8080} {--force}';
        $query = 'deploy staging --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('staging', $result->defaults->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_removes_and_adds_parsers(): void
    {
        $initialCount = count($this->parser->getParsers());

        $this->parser->removeParser('AndyDefer\SignatureParser\Parsers\FlagParser');
        $this->assertCount($initialCount - 1, $this->parser->getParsers());

        $this->parser->addParser(new CustomParser);
        $this->assertCount($initialCount, $this->parser->getParsers());
    }

    // ==================== ORDER VALIDATION TESTS ====================

    public function test_throws_exception_for_invalid_order_required_after_default(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature order');

        $signature = 'backup {format=zip} {source}';
        $query = 'backup tar.gz /var/www';

        $this->parser->parse($signature, $query);
    }

    public function test_throws_exception_for_invalid_order_required_after_variadic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature order');

        $signature = 'backup {excludes*} {source}';
        $query = 'backup [cache, logs] /var/www';

        $this->parser->parse($signature, $query);
    }

    public function test_throws_exception_for_invalid_order_default_after_variadic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature order');

        $signature = 'backup {excludes*} {format=zip}';
        $query = 'backup [cache, logs] tar.gz';

        $this->parser->parse($signature, $query);
    }

    public function test_throws_exception_for_invalid_order_argument_after_flag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature order');

        $signature = 'backup {--force} {source}';
        $query = 'backup --force /var/www';

        $this->parser->parse($signature, $query);
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

    public function test_valid_order_required_then_default_then_variadic(): void
    {
        $signature = 'process {name} {format=zip} {files*} {--verbose}';
        $query = 'process build tar.gz [file1, file2] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('process', $result->source);
        $this->assertSame('build', $result->requireds->first()->value);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertSame(['file1', 'file2'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    // ==================== SIGNATURE VALIDATION TESTS ====================

    public function test_signature_validation_passes_for_valid_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {excludes*} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_signature_validation_passes_with_nullable_arguments(): void
    {
        $signature = 'deploy {env=?} {port=?} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_signature_validation_fails_for_empty_signature(): void
    {
        $result = $this->parser->validateSignature('');

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('empty', $result->errors->first());
    }

    public function test_signature_validation_fails_for_duplicate_argument_names(): void
    {
        $signature = 'backup {source} {source} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('duplicate', strtolower($result->errors->first()));
    }

    public function test_is_signature_valid_returns_boolean(): void
    {
        $validSignature = 'backup {source} {destination} {--force}';
        $invalidSignature = 'backup {source} {invalid!} {--force}';

        $this->assertTrue($this->parser->isSignatureValid($validSignature));
        $this->assertFalse($this->parser->isSignatureValid($invalidSignature));
    }

    public function test_signature_validation_detects_unknown_token_type(): void
    {
        $signature = 'backup {source} {??} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('??', $result->errors->first());
    }

    public function test_signature_validation_handles_complex_signature(): void
    {
        $signature = 'backup {source} {destination} {format=zip} {output=dist} {excludes*} {purpose*} {--force} {--verbose}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
        $this->assertCount(0, $result->suggestions);
    }

    public function test_signature_validation_fails_for_source_with_invalid_characters(): void
    {
        $signature = 'backup! {source} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('source name', $result->errors->first());
    }

    public function test_signature_validation_passes_for_source_with_hyphens(): void
    {
        $signature = 'my-backup {source} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertCount(0, $result->errors);
    }

    public function test_signature_validation_fails_for_variadic_without_name(): void
    {
        $signature = 'backup {*} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
    }

    public function test_signature_validation_fails_for_default_without_name(): void
    {
        $signature = 'backup {=zip} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
    }

    public function test_signature_validation_fails_for_flag_without_name(): void
    {
        $signature = 'backup {--} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
    }

    public function test_signature_validation_provides_suggestions_for_invalid_tokens(): void
    {
        $signature = 'backup {source} {??} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->suggestions);
        $this->assertStringContainsString('Check the syntax', $result->suggestions->first());
    }

    public function test_signature_validation_fails_for_invalid_order(): void
    {
        $signature = 'backup {format=zip} {source} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Required argument', $result->errors->first());
    }

    public function test_signature_validation_fails_for_invalid_token_syntax(): void
    {
        $signature = 'backup {source} {format=zip} {invalid!} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
        $this->assertStringContainsString('invalid!', $result->errors->first());
    }

    public function test_signature_validation_fails_for_duplicate_names_across_types(): void
    {
        $signature = 'backup {source} {source=zip} {source*} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
        $this->assertStringContainsString('duplicate', strtolower($result->errors->first()));
    }

    public function test_message_parser(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World"> <later="goodby">';

        $result = $this->parser->parse($signature, $query);

        $data = $result->custom_data->toArray();

        $this->assertSame('Hello World', $data['greeting']);
        $this->assertSame('goodby', $data['later']);

        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    // ==================== TESTS: Custom Parser Integration ====================

    public function test_parse_with_custom_tag_parser_preserves_standard_components(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('greeting', $data);
        $this->assertSame('Hello World', $data['greeting']);
    }

    public function test_parse_with_multiple_custom_tags(): void
    {
        $signature = 'deploy {environment} {--force}';
        $query = 'deploy staging --force <version="1.2.3"> <user="admin">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('staging', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('version', $data);
        $this->assertSame('1.2.3', $data['version']);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('admin', $data['user']);
    }

    public function test_parse_with_custom_tags_and_variadic_arguments(): void
    {
        $signature = 'process {files*} {--verbose}';
        $query = 'process [file1.txt, file2.txt] --verbose <format="json">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('process', $result->source);
        $this->assertCount(2, $result->variadics->first()->values);
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('format', $data);
        $this->assertSame('json', $data['format']);
    }

    public function test_parse_with_custom_tags_and_default_arguments(): void
    {
        $signature = 'backup {source} {format=zip} {--force}';
        $query = 'backup /var/www --force <compression="gzip">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->requireds->first()->value);
        $this->assertSame('zip', $result->defaults->first()->value);
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('compression', $data);
        $this->assertSame('gzip', $data['compression']);
    }

    public function test_parse_with_only_custom_tags(): void
    {
        $signature = 'version';
        $query = 'version <build="123"> <commit="abc">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('version', $result->source);
        $this->assertEmpty($result->requireds);
        $this->assertEmpty($result->defaults);
        $this->assertEmpty($result->variadics);
        $this->assertEmpty($result->flags);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('build', $data);
        $this->assertSame('123', $data['build']);
        $this->assertArrayHasKey('commit', $data);
        $this->assertSame('abc', $data['commit']);
    }

    public function test_parse_custom_tags_preserves_query_order(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello"> <later="goodby">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('greeting', $data);
        $this->assertSame('Hello', $data['greeting']);
        $this->assertArrayHasKey('later', $data);
        $this->assertSame('goodby', $data['later']);
    }

    public function test_validate_with_custom_tags_returns_valid(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World">';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_with_invalid_custom_tags_returns_errors(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World" <invalid>';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
    }

    // ==================== ENUM TESTS ====================

    public function test_parse_enum_with_default_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level master';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertNotEmpty($result->enums);
        $this->assertSame('master', $result->enums->get('level'));
        $this->assertSame(['beginner', 'middle', 'master'], $result->enums->getAllowedValues('level'));
    }

    public function test_parse_enum_with_default_value_when_not_provided(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertSame('middle', $result->enums->get('level'));
    }

    public function test_parse_enum_with_optional_and_tilde(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=?';
        $query = 'set-level ~';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertNull($result->enums->get('level'));
    }

    public function test_parse_enum_with_required(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';
        $query = 'set-level beginner';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertSame('beginner', $result->enums->get('level'));
    }

    public function test_parse_multiple_enums(): void
    {
        $signature = 'config ::level->[low,medium,high]=medium ::mode->[dev,staging,prod]=dev';
        $query = 'config high staging';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('config', $result->source);
        $this->assertSame('high', $result->enums->get('level'));
        $this->assertSame('staging', $result->enums->get('mode'));
    }

    public function test_parse_enum_with_flags(): void
    {
        $signature = 'deploy ::env->[dev,staging,prod]=staging --force';
        $query = 'deploy prod --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('prod', $result->enums->get('env'));
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_enum_with_custom_tags(): void
    {
        $signature = 'send ::priority->[low,medium,high]=medium --verbose';
        $query = 'send high --verbose <user="admin">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('send', $result->source);
        $this->assertSame('high', $result->enums->get('priority'));
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertSame('admin', $data['user']);
    }

    public function test_parse_enum_with_required_and_missing_value_returns_null(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';
        $query = 'set-level';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertNull($result->enums->get('level'));
    }

    public function test_parse_enum_with_invalid_value_uses_default(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level expert';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertSame('middle', $result->enums->get('level'));
    }

    public function test_enum_parser_removes_enum_token_from_signature(): void
    {
        $parser = new EnumParser;
        $signature = ['set-level', '::level->[beginner,middle,master]=middle', '--verbose'];
        $query = ['set-level', 'master', '--verbose'];

        $result = $parser->parse($signature, $query);

        $this->assertSame(['set-level', '--verbose'], $result->signature->toArray());
        $this->assertSame(['set-level', '--verbose'], $result->query->toArray());
    }

    public function test_validate_enum_returns_valid_for_correct_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level master';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_enum_returns_valid_for_default_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_enum_returns_invalid_for_invalid_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level expert';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid value', $result->errors->first());
    }

    public function test_validate_enum_returns_invalid_for_required_missing(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';
        $query = 'set-level';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Missing required', $result->errors->first());
    }

    public function test_validate_enum_returns_invalid_for_tilde_on_non_optional(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';
        $query = 'set-level ~';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Cannot use '~'", $result->errors->first());
    }

    public function test_validate_enum_returns_invalid_for_invalid_default(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=expert';
        $query = 'set-level';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Default value', $result->errors->first());
    }

    public function test_validate_enum_with_empty_allowed_values_returns_error(): void
    {
        $signature = 'set-level ::level->[]=*';
        $query = 'set-level value';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('no allowed values', $result->errors->first());
    }

    public function test_enum_collection_methods(): void
    {
        $signature = 'config ::level->[low,medium,high]=medium ::mode->[dev,staging,prod]=dev';
        $query = 'config high staging';

        $result = $this->parser->parse($signature, $query);

        $enum = $result->enums;

        $this->assertTrue($enum->has('level'));
        $this->assertTrue($enum->has('mode'));
        $this->assertFalse($enum->has('unknown'));

        $this->assertSame('high', $enum->get('level'));
        $this->assertSame('staging', $enum->get('mode'));
        $this->assertNull($enum->get('unknown'));

        $this->assertSame(['level', 'mode'], $enum->getNames());
        $this->assertSame(['high', 'staging'], $enum->getValues());

        $this->assertSame(['low', 'medium', 'high'], $enum->getAllowedValues('level'));
        $this->assertSame(['dev', 'staging', 'prod'], $enum->getAllowedValues('mode'));
        $this->assertNull($enum->getAllowedValues('unknown'));

        $this->assertTrue($enum->isAllowed('level', 'high'));
        $this->assertFalse($enum->isAllowed('level', 'unknown'));

        $this->assertFalse($enum->isRequired('level'));
        $this->assertFalse($enum->isRequired('mode'));

        $this->assertFalse($enum->isOptional('level'));
        $this->assertFalse($enum->isOptional('mode'));

        $this->assertTrue($enum->hasDefault('level'));
        $this->assertTrue($enum->hasDefault('mode'));
        $this->assertFalse($enum->hasDefault('unknown'));

        $this->assertSame('medium', $enum->getDefault('level'));
        $this->assertSame('dev', $enum->getDefault('mode'));
    }

    public function test_enum_with_required_state(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=*';
        $query = 'set-level master';

        $result = $this->parser->parse($signature, $query);

        $this->assertTrue($result->enums->isRequired('level'));
        $this->assertFalse($result->enums->isOptional('level'));
        $this->assertFalse($result->enums->hasDefault('level'));
    }

    public function test_enum_with_optional_state(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=?';
        $query = 'set-level ~';

        $result = $this->parser->parse($signature, $query);

        $this->assertFalse($result->enums->isRequired('level'));
        $this->assertTrue($result->enums->isOptional('level'));
        $this->assertFalse($result->enums->hasDefault('level'));
    }

    public function test_enum_with_defaulted_state(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle';
        $query = 'set-level';

        $result = $this->parser->parse($signature, $query);

        $this->assertFalse($result->enums->isRequired('level'));
        $this->assertFalse($result->enums->isOptional('level'));
        $this->assertTrue($result->enums->hasDefault('level'));
        $this->assertSame('middle', $result->enums->getDefault('level'));
    }

    public function test_enum_to_associative_array(): void
    {
        $signature = 'config ::level->[low,medium,high]=medium ::mode->[dev,staging,prod]=dev';
        $query = 'config high staging';

        $result = $this->parser->parse($signature, $query);

        $array = $result->enums->toAssociativeArray();

        $this->assertSame(['level' => 'high', 'mode' => 'staging'], $array);
    }

    public function test_enum_to_full_array(): void
    {
        $signature = 'config ::level->[low,medium,high]=medium ::mode->[dev,staging,prod]=dev';
        $query = 'config high staging';

        $result = $this->parser->parse($signature, $query);

        $fullArray = $result->enums->toFullArray();

        $this->assertCount(2, $fullArray);
        $this->assertSame('level', $fullArray[0]['name']);
        $this->assertSame('high', $fullArray[0]['value']);
        $this->assertSame(['low', 'medium', 'high'], $fullArray[0]['allowed_values']);
        $this->assertSame('medium', $fullArray[0]['default_value']);
    }

    // ==================== RESTRICTED VARIADIC TESTS ====================

    public function test_parse_restricted_variadic_with_valid_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin,editor]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(1, $result->variadics);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
    }

    public function test_parse_restricted_variadic_with_single_value(): void
    {
        $signature = 'command {format*>[json,xml,yaml]}';
        $query = 'command [json]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(1, $result->variadics);
        $this->assertSame(['json'], $result->variadics->first()->values->toArray());
    }

    public function test_parse_restricted_variadic_with_empty_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command []';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(1, $result->variadics);
        $this->assertSame([], $result->variadics->first()->values->toArray());
    }

    public function test_parse_restricted_variadic_with_spaces_in_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin, editor]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
    }

    public function test_parse_restricted_variadic_with_flags(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]} {--verbose}';
        $query = 'command [admin,editor] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_restricted_variadic_with_custom_tags(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]} {--verbose}';
        $query = 'command [admin] --verbose <user="admin">';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame(['admin'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);

        $data = $result->custom_data->toArray();
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('admin', $data['user']);
    }

    public function test_parse_multiple_restricted_variadics(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]} {format*>[json,xml,yaml]}';
        $query = 'command [admin,editor] [json]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(2, $result->variadics);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
        $this->assertSame(['json'], $result->variadics->last()->values->toArray());
    }

    public function test_parse_mixed_variadic_types(): void
    {
        $signature = 'command {roles*>[admin,editor]} {tags*} {--verbose}';
        $query = 'command [admin] [tag1,tag2] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(2, $result->variadics);
        $this->assertSame(['admin'], $result->variadics->first()->values->toArray());
        $this->assertSame(['tag1', 'tag2'], $result->variadics->last()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_restricted_variadic_with_underscores_in_name(): void
    {
        $signature = 'command {user_role*>[admin,editor,viewer]}';
        $query = 'command [admin,editor]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(1, $result->variadics);
        $this->assertSame('user_role', $result->variadics->first()->name);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
    }

    public function test_parse_restricted_variadic_throws_exception_on_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "guest" not allowed for "roles"');

        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin,guest]';

        $this->parser->parse($signature, $query);
    }

    public function test_parse_restricted_variadic_throws_exception_on_multiple_invalid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "guest" not allowed for "roles"');

        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [guest,unknown]';

        $this->parser->parse($signature, $query);
    }

    public function test_parse_restricted_variadic_with_empty_restrictions(): void
    {
        $signature = 'command {roles*>[]}';
        $query = 'command [admin,editor]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertCount(1, $result->variadics);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
    }

    // ==================== RESTRICTED VARIADIC VALIDATION TESTS ====================

    public function test_validate_restricted_variadic_returns_valid_for_valid_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin,editor]';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_restricted_variadic_returns_valid_for_single_value(): void
    {
        $signature = 'command {format*>[json,xml,yaml]}';
        $query = 'command [json]';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_restricted_variadic_returns_valid_for_empty_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command []';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_restricted_variadic_returns_valid_for_spaces_in_query(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin, editor]';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_restricted_variadic_returns_invalid_for_invalid_value(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin,guest]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'guest' not allowed for 'roles'", $result->errors->first());
        $this->assertStringContainsString('admin, editor, viewer', $result->suggestions->first());
    }

    public function test_validate_restricted_variadic_returns_invalid_for_multiple_invalid_values(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [guest,unknown]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
    }

    public function test_validate_restricted_variadic_returns_invalid_for_empty_allowed_values(): void
    {
        $signature = 'command {roles*>[]}';
        $query = 'command [admin]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('no allowed values', $result->errors->first());
    }

    public function test_validate_restricted_variadic_returns_invalid_for_invalid_single_value(): void
    {
        $signature = 'command {format*>[json,xml,yaml]}';
        $query = 'command [html]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'html' not allowed for 'format'", $result->errors->first());
        $this->assertStringContainsString('json, xml, yaml', $result->suggestions->first());
    }

    public function test_validate_multiple_restricted_variadics_passes_with_valid_values(): void
    {
        $signature = 'command {roles*>[admin,editor]} {format*>[json,xml]}';
        $query = 'command [admin,editor] [json]';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_multiple_restricted_variadics_fails_with_invalid_values(): void
    {
        $signature = 'command {roles*>[admin,editor]} {format*>[json,xml]}';
        $query = 'command [admin,guest] [html]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
        $this->assertStringContainsString("Value 'guest' not allowed for 'roles'", $result->errors->first());
    }

    public function test_validate_mixed_variadic_types_passes(): void
    {
        $signature = 'command {roles*>[admin,editor]} {tags*} {--verbose}';
        $query = 'command [admin] [tag1,tag2] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_mixed_variadic_types_fails_on_restricted(): void
    {
        $signature = 'command {roles*>[admin,editor]} {tags*} {--verbose}';
        $query = 'command [guest] [tag1,tag2] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'guest' not allowed for 'roles'", $result->errors->first());
    }

    public function test_validate_restricted_variadic_returns_invalid_for_empty_value_in_list(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}';
        $query = 'command [admin, , editor]';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Empty value', $result->errors->first());
    }

    // ==================== SIGNATURE VALIDATION FOR RESTRICTED VARIADICS ====================

    public function test_signature_validation_passes_for_restricted_variadic(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]} {--verbose}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_passes_for_multiple_restricted_variadics(): void
    {
        $signature = 'command {roles*>[admin,editor]} {format*>[json,xml]}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_passes_for_mixed_variadics(): void
    {
        $signature = 'command {roles*>[admin,editor]} {tags*}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_passes_for_restricted_variadic_with_spaces(): void
    {
        $signature = 'command {roles*>[admin, editor, viewer]}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_passes_for_restricted_variadic_with_empty_allowed(): void
    {
        $signature = 'command {roles*>[]}';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_fails_for_restricted_variadic_without_star(): void
    {
        $signature = 'command {roles>[admin,editor]}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid token syntax', $result->errors->first());
    }

    public function test_signature_validation_fails_for_restricted_variadic_with_invalid_name(): void
    {
        $signature = 'command {123roles*>[admin,editor]}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid token syntax', $result->errors->first());
    }

    // ==================== RESTRICTED VARIADIC WITH OTHER COMPONENTS ====================

    public function test_parse_signature_with_restricted_variadic_and_required_args(): void
    {
        $signature = 'command {source} {roles*>[admin,editor,viewer]} {--verbose}';
        $query = 'command /var/www [admin,editor] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('/var/www', $result->requireds->first()->value);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_restricted_variadic_and_default_args(): void
    {
        $signature = 'command {format=zip} {roles*>[admin,editor,viewer]}';
        $query = 'command tar.gz [admin]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertSame(['admin'], $result->variadics->first()->values->toArray());
    }

    public function test_parse_signature_with_restricted_variadic_and_enum(): void
    {
        $signature = 'command ::level->[low,medium,high]=medium {roles*>[admin,editor,viewer]}';
        $query = 'command high [admin,editor]';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('high', $result->enums->get('level'));
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
    }

    public function test_validate_restricted_variadic_works_with_all_components(): void
    {
        $signature = 'command {source} {format=zip} ::level->[low,high]=low {roles*>[admin,editor]} {--verbose}';
        $query = 'command /var/www tar.gz high [admin] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_restricted_variadic_fails_with_all_components(): void
    {
        $signature = 'command {source} {format=zip} ::level->[low,high]=low {roles*>[admin,editor]} {--verbose}';
        $query = 'command /var/www tar.gz high [guest] --verbose';

        $result = $this->parser->validate($signature, $query);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString("Value 'guest' not allowed for 'roles'", $result->errors->first());
    }

    // ==================== COMMENT TESTS ====================

    public function test_parse_signature_with_comments_on_required_argument(): void
    {
        $signature = 'command {name}#"The name of the user" {--verbose}';
        $query = 'command John --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_on_default_argument(): void
    {
        $signature = 'command {format=zip}#"The output format" {--verbose}';
        $query = 'command tar.gz --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_on_restricted_variadic(): void
    {
        $signature = 'command {roles*>[admin,editor,viewer]}#"The allowed roles" {--verbose}';
        $query = 'command [admin,editor] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame(['admin', 'editor'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_on_enum(): void
    {
        $signature = 'command ::level->[low,medium,high]=*#"The priority level" {--verbose}';
        $query = 'command high --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('high', $result->enums->get('level'));
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_on_flag(): void
    {
        $signature = 'command {--force}#"Force the operation" {--verbose}';
        $query = 'command --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_parse_signature_with_comments_using_single_quotes(): void
    {
        $signature = "command {name}#'The user name' {--verbose}";
        $query = 'command John --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_multiple_comments(): void
    {
        $signature = 'command {name}#"The user name" {role*>[admin,editor]}#"The user role" {--verbose}#"Show details"';
        $query = 'command John [admin] --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertSame(['admin'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_containing_special_characters(): void
    {
        $signature = 'command {name}#"User name (e.g., John Doe)" {--force}#"Force mode (use with caution!)"';
        $query = 'command John --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_signature_with_comments_and_whitespace(): void
    {
        $signature = 'command {name}#"The user name" {--verbose}#"Show details"';
        $query = 'command John --verbose';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_validate_signature_with_comments_returns_valid(): void
    {
        $signature = 'command {name}#"User name" {role*>[admin,editor]}#"Role" {--force}#"Force mode"';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_validate_with_query_and_comments_returns_valid(): void
    {
        $signature = 'command {name}#"User name" {--force}#"Force mode"';
        $query = 'command John --force';

        $result = $this->parser->validate($signature, $query);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_parse_signature_with_comments_preserves_correct_parsing(): void
    {
        $signature = 'backup {source}#"Source directory" {destination}#"Destination directory" {format=zip}#"Archive format" {excludes*}#"Files to exclude" {--force}#"Force overwrite"';
        $query = 'backup /var/www /backup tar.gz [cache, logs] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('backup', $result->source);
        $this->assertSame('/var/www', $result->requireds->first()->value);
        $this->assertSame('/backup', $result->requireds->last()->value);
        $this->assertSame('tar.gz', $result->defaults->first()->value);
        $this->assertSame(['cache', 'logs'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_parse_complex_signature_with_comments(): void
    {
        $signature = 'deploy {environment}#"Target environment (staging/prod)" {version=latest}#"Version to deploy" {--force}#"Skip confirmation" {--dry-run}#"Simulate deployment"';
        $query = 'deploy staging v1.2.3 --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('staging', $result->requireds->first()->value);
        $this->assertSame('v1.2.3', $result->defaults->first()->value);
        $this->assertTrue($result->flags->first()->value);
        $this->assertFalse($result->flags->last()->value);
    }

    public function test_parse_with_comments_and_custom_tags(): void
    {
        $signature = 'send {recipient}#"The recipient email" {--verbose}#"Show details"';
        $query = 'send John --verbose <greeting="Hello World"> <later="goodby">';

        $result = $this->parser->parse($signature, $query);

        $data = $result->custom_data->toArray();

        $this->assertSame('Hello World', $data['greeting']);
        $this->assertSame('goodby', $data['later']);
        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    public function test_signature_validation_passes_with_comments_on_all_argument_types(): void
    {
        $signature = 'command {name}#"User name" {format=zip}#"Output format"  ::level->[low,high]#"Priority" {roles*>[admin,editor]}#"Roles"  {--force}#"Force"';

        $result = $this->parser->validateSignature($signature);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function test_signature_validation_fails_with_invalid_syntax_even_with_comments(): void
    {
        $signature = 'command {name}#"User name" {invalid!} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Invalid token syntax', $result->errors->first());
    }

    public function test_parse_enum_with_comment_and_default_value(): void
    {
        $signature = 'set-level ::level->[beginner,middle,master]=middle#"The user skill level"';
        $query = 'set-level master';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('set-level', $result->source);
        $this->assertSame('master', $result->enums->get('level'));
        $this->assertSame(['beginner', 'middle', 'master'], $result->enums->getAllowedValues('level'));
    }

    public function test_parse_with_comments_and_duplicate_names_detection(): void
    {
        $signature = 'command {name}#"User name" {name=default}#"Default name"';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Duplicate argument name', $result->errors->first());
    }

    public function test_signature_validation_detects_invalid_order_with_comments(): void
    {
        $signature = 'command {format=zip}#"Format" {name}#"Name" {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Required argument', $result->errors->first());
    }

    public function test_clean_signature_comments_removes_all_comments(): void
    {
        $signature = 'command {name}#"User name" {role*>[admin,editor]}#"Roles" {--force}#"Force"';
        $query = 'command John [admin] --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('command', $result->source);
        $this->assertSame('John', $result->requireds->first()->value);
        $this->assertSame(['admin'], $result->variadics->first()->values->toArray());
        $this->assertTrue($result->flags->first()->value);
    }
}
