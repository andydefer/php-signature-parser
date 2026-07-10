<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
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

    public function test_parses_with_default_and_nullable_mixed(): void
    {
        $signature = 'deploy {port=8080} {--force}';
        $query = 'deploy staging --force';

        $result = $this->parser->parse($signature, $query);

        $this->assertSame('deploy', $result->source);
        $this->assertSame('staging', $result->default->first()->value);
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
        $this->assertSame('build', $result->required->first()->value);
        $this->assertSame('tar.gz', $result->default->first()->value);
        $this->assertSame(['file1', 'file2'], $result->variadic->first()->values->toArray());
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
        // Il peut y avoir plusieurs erreurs (ordre + token invalide)
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
        $this->assertStringContainsString('invalid!', $result->errors->first());
    }

    public function test_signature_validation_fails_for_duplicate_names_across_types(): void
    {
        $signature = 'backup {source} {source=zip} {source*} {--force}';

        $result = $this->parser->validateSignature($signature);

        $this->assertFalse($result->isValid);
        // Multiple duplicates detected
        $this->assertGreaterThanOrEqual(1, $result->errors->count());
        $this->assertStringContainsString('duplicate', strtolower($result->errors->first()));
    }

    public function test_message_parser(): void
    {

        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World"> <later="goodby">';

        $result = $this->parser->parse($signature, $query);

        // ✅ Accès aux données personnalisées
        $data = $result->custom_data->toArray();

        $this->assertSame('Hello World', $data['greeting']);
        $this->assertSame('goodby', $data['later']);

        // ✅ Accès aux données standards (la requête a été nettoyée)
        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->required->first()->value);
        $this->assertTrue($result->flags->first()->value);
    }

    // ==================== TESTS: Custom Parser Integration ====================

    public function test_parse_with_custom_tag_parser_preserves_standard_components(): void
    {
        $signature = 'send {recipient} {--verbose}';
        $query = 'send John --verbose <greeting="Hello World">';

        $result = $this->parser->parse($signature, $query);

        // ✅ Standard components
        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->required->first()->value);
        $this->assertTrue($result->flags->first()->value);

        // ✅ Custom data
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
        $this->assertSame('staging', $result->required->first()->value);
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
        $this->assertCount(2, $result->variadic->first()->values);
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
        $this->assertSame('/var/www', $result->required->first()->value);
        $this->assertSame('zip', $result->default->first()->value);
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
        $this->assertEmpty($result->required);
        $this->assertEmpty($result->default);
        $this->assertEmpty($result->variadic);
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

        // ✅ Données standards
        $this->assertSame('send', $result->source);
        $this->assertSame('John', $result->required->first()->value);
        $this->assertTrue($result->flags->first()->value);

        // ✅ Données personnalisées
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
}
