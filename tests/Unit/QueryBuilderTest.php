<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\SignatureParser\QueryBuilder;
use AndyDefer\SignatureParser\ValueObjects\SignatureStructureVO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    // ==================== TESTS: Initialization ====================

    public function test_init_with_valid_signature_creates_builder(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->assertInstanceOf(QueryBuilder::class, $builder);
        $this->assertSame('greet', $builder->getSource());
        $this->assertInstanceOf(SignatureStructureVO::class, $builder->getStructure());
    }

    public function test_init_with_invalid_signature_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QueryBuilder::init('greet {name} {--invalid="Hello"}');
    }

    public function test_init_with_initial_query_populates_builder(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}', 'greet John --formal');

        $this->assertSame('John', $builder->getArgument('name'));
        $this->assertTrue($builder->hasFlag('--formal'));
    }

    public function test_init_with_initial_query_without_flags(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}', 'greet John');

        $this->assertSame('John', $builder->getArgument('name'));
        $this->assertFalse($builder->hasFlag('--formal'));
    }

    // ==================== TESTS: setArgument ====================

    public function test_set_argument_sets_value(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setArgument('name', 'Alice');

        $this->assertSame('Alice', $builder->getArgument('name'));
    }

    public function test_set_argument_with_null_uses_default(): void
    {
        $builder = QueryBuilder::init('greet {name=World} {--formal}');
        $builder->setArgument('name', null);

        $this->assertSame('World', $builder->getArgument('name'));
    }

    public function test_set_argument_with_null_on_nullable_uses_tilde(): void
    {
        $builder = QueryBuilder::init('greet {name=?} {--formal}');
        $builder->setArgument('name', null);

        $this->assertSame('~', $builder->getArgument('name'));
    }

    public function test_set_argument_with_empty_string_on_default_uses_tilde(): void
    {
        $signature = 'greet {name=World} {--formal}';
        $builder = QueryBuilder::init($signature);
        $builder->setArgument('name', '');

        $this->assertSame('~', $builder->getArgument('name'));
    }

    public function test_set_argument_with_nonexistent_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "age" does not exist in signature');

        $builder->setArgument('age', '25');
    }

    public function test_set_argument_with_default_value_from_signature(): void
    {
        $builder = QueryBuilder::init('backup {source} {format=zip}');
        $builder->setArgument('format', 'tar');

        $this->assertSame('tar', $builder->getArgument('format'));
    }

    public function test_set_argument_with_variadic(): void
    {
        $builder = QueryBuilder::init('process {files*}');
        $builder->setArgument('files', 'file1.txt file2.txt');

        $this->assertSame('file1.txt file2.txt', $builder->getArgument('files'));
    }

    // ==================== TESTS: setRequired ====================

    public function test_set_required_sets_value(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setRequired('name', 'John');

        $this->assertSame('John', $builder->getArgument('name'));
    }

    public function test_set_required_with_nonexistent_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "age" is not a required argument in the signature');

        $builder->setRequired('age', '25');
    }

    public function test_set_required_with_null_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required argument "name" cannot be null');

        $builder->setArgument('name', null);
    }

    // ==================== TESTS: setDefault ====================

    public function test_set_default_sets_value(): void
    {
        $builder = QueryBuilder::init('backup {format=zip}');
        $builder->setDefault('format', 'tar');

        $this->assertSame('tar', $builder->getArgument('format'));
    }

    public function test_set_default_with_null_restores_default(): void
    {
        $builder = QueryBuilder::init('backup {format=zip}');
        $builder->setDefault('format', 'tar');
        $builder->setDefault('format', null);

        $this->assertSame('zip', $builder->getArgument('format'));
    }

    public function test_set_default_with_nonexistent_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "format" is not a default argument in the signature');

        $builder->setDefault('format', 'tar');
    }

    public function test_set_default_with_null_on_nullable_uses_tilde(): void
    {
        $builder = QueryBuilder::init('backup {format=?}');
        $builder->setDefault('format', null);

        $this->assertSame('~', $builder->getArgument('format'));
    }

    // ==================== TESTS: setVariadic ====================

    public function test_set_variadic_sets_value(): void
    {
        $builder = QueryBuilder::init('process {files*}');
        $builder->setVariadic('files', 'file1.txt file2.txt');

        $this->assertSame('file1.txt file2.txt', $builder->getArgument('files'));
    }

    public function test_set_variadic_with_nonexistent_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "files" is not a variadic argument in the signature');

        $builder->setVariadic('files', 'file1.txt');
    }

    // ==================== TESTS: setFlag ====================

    public function test_set_flag_sets_value(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setFlag('--formal', true);

        $this->assertTrue($builder->hasFlag('--formal'));
    }

    public function test_set_flag_without_value_defaults_to_true(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setFlag('--formal');

        $this->assertTrue($builder->hasFlag('--formal'));
    }

    public function test_set_flag_to_false(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setFlag('--formal', false);

        $this->assertFalse($builder->hasFlag('--formal'));
    }

    public function test_set_flag_with_nonexistent_flag_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flag "--verbose" does not exist in signature');

        $builder->setFlag('--verbose', true);
    }

    // ==================== TESTS: toggleFlag ====================

    public function test_toggle_flag(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setFlag('--formal', false);
        $builder->toggleFlag('--formal');

        $this->assertTrue($builder->hasFlag('--formal'));
    }

    public function test_toggle_flag_twice(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setFlag('--formal', false);
        $builder->toggleFlag('--formal');
        $builder->toggleFlag('--formal');

        $this->assertFalse($builder->hasFlag('--formal'));
    }

    public function test_toggle_flag_nonexistent_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Flag "--verbose" does not exist in signature');

        $builder->toggleFlag('--verbose');
    }

    // ==================== TESTS: getters ====================

    public function test_get_arguments_returns_all_arguments(): void
    {
        $builder = QueryBuilder::init('greet {name} {age=?} {format=json} {--formal}');
        $builder->setArgument('name', 'John');
        $builder->setArgument('age', '30');
        $builder->setArgument('format', 'xml');

        $arguments = $builder->getArguments();

        $this->assertEquals([
            'age' => '30',
            'format' => 'xml',
            'name' => 'John',
        ], $arguments);
    }

    public function test_get_flags_returns_all_flags(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal} {--verbose}');
        $builder->setFlag('--formal', true);
        $builder->setFlag('--verbose', false);

        $flags = $builder->getFlags();

        $this->assertEquals([
            '--formal' => true,
            '--verbose' => false,
        ], $flags);
    }

    public function test_get_argument_returns_null_for_nonexistent(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->assertNull($builder->getArgument('age'));
    }

    // ==================== TESTS: reset ====================

    public function test_reset_restores_default_values(): void
    {
        $builder = QueryBuilder::init('greet {name} {format=json} {--formal}');
        $builder->setArgument('name', 'John');
        $builder->setArgument('format', 'xml');
        $builder->setFlag('--formal', true);

        $builder->reset();

        $this->assertNull($builder->getArgument('name'));
        $this->assertSame('json', $builder->getArgument('format'));
        $this->assertFalse($builder->hasFlag('--formal'));
    }

    public function test_reset_clears_flags(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal} {--verbose}');
        $builder->setFlag('--formal', true);
        $builder->setFlag('--verbose', true);

        $builder->reset();

        $this->assertFalse($builder->hasFlag('--formal'));
        $this->assertFalse($builder->hasFlag('--verbose'));
    }

    public function test_reset_restores_nullable_default_to_tilde(): void
    {
        $builder = QueryBuilder::init('greet {name=?} {--formal}');
        $builder->setArgument('name', 'John');
        $builder->setFlag('--formal', true);

        $builder->reset();

        $this->assertSame('~', $builder->getArgument('name'));
        $this->assertFalse($builder->hasFlag('--formal'));
    }

    // ==================== TESTS: validate & isValid ====================

    public function test_validate_returns_valid_for_correct_query(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');
        $builder->setArgument('name', 'John');
        $builder->setFlag('--formal', true);

        $result = $builder->validate();

        $this->assertTrue($result->isValid);
        $this->assertTrue($builder->isValid());
    }

    public function test_validate_returns_invalid_for_missing_required_argument(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $result = $builder->validate();

        $this->assertFalse($result->isValid);
        $this->assertFalse($builder->isValid());
        $this->assertNotEmpty($builder->getErrors());
    }

    public function test_get_errors_returns_collection(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $errors = $builder->getErrors();

        $this->assertInstanceOf(StringTypedCollection::class, $errors);
        $this->assertNotEmpty($errors);
    }

    // ==================== TESTS: build ====================

    public function test_build_returns_correct_query_string(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?} {format=text} {--unique-only} {--recurring-only} {--verbose}');
        $builder->setArgument('limit', '3');
        $builder->setArgument('format', 'json');
        $builder->setFlag('--unique-only', true);
        $builder->setFlag('--verbose', true);

        $query = $builder->build();

        $this->assertSame('process-tasks 3 json --unique-only --verbose', $query);
    }

    public function test_build_without_flags(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?} {format=text} {--unique-only}');
        $builder->setArgument('limit', '5');

        $query = $builder->build();

        $this->assertSame('process-tasks 5 text', $query);
    }

    public function test_build_with_tilde_for_nullable(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?} {format=text}');

        $query = $builder->build();

        $this->assertSame('process-tasks ~ text', $query);
    }

    public function test_build_with_default_values(): void
    {
        $builder = QueryBuilder::init('backup {source} {format=zip}');

        $query = $builder->build();

        $this->assertSame('backup ~ zip', $query);
    }

    public function test_build_throws_exception_for_invalid_query(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);

        $builder->build();
    }

    public function test_build_with_variadic_arguments(): void
    {
        $builder = QueryBuilder::init('process {files*} {--verbose}');
        $builder->setArgument('files', 'file1.txt file2.txt file3.txt');
        $builder->setFlag('--verbose', true);

        $query = $builder->build();

        $this->assertSame('process [file1.txt file2.txt file3.txt] --verbose', $query);
    }

    public function test_build_with_complex_signature(): void
    {
        $builder = QueryBuilder::init(
            'backup {source} {destination} {format=zip} {env=?} {excludes*} {--force} {--verbose}'
        );

        $builder->setArgument('source', '/var/www');
        $builder->setArgument('destination', '/backup');
        $builder->setArgument('format', 'tar.gz');
        $builder->setArgument('env', 'staging');
        $builder->setArgument('excludes', 'cache logs tmp');
        $builder->setFlag('--force', true);

        $query = $builder->build();

        $this->assertSame(
            'backup /var/www /backup tar.gz staging [cache logs tmp] --force',
            $query
        );
    }

    // ==================== TESTS: clone ====================

    public function test_clone_creates_independent_builder(): void
    {
        $builder1 = QueryBuilder::init('greet {name} {--formal}');
        $builder1->setArgument('name', 'John');
        $builder1->setFlag('--formal', true);

        $builder2 = clone $builder1;
        $builder2->setArgument('name', 'Alice');
        $builder2->setFlag('--formal', false);

        $this->assertSame('John', $builder1->getArgument('name'));
        $this->assertSame('Alice', $builder2->getArgument('name'));
        $this->assertTrue($builder1->hasFlag('--formal'));
        $this->assertFalse($builder2->hasFlag('--formal'));
    }

    // ==================== TESTS: Edge cases ====================

    public function test_empty_initial_query(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}', '');

        $this->assertSame('~', $builder->getArgument('name'));
        $this->assertFalse($builder->hasFlag('--formal'));
    }

    public function test_set_argument_with_empty_string(): void
    {
        $builder = QueryBuilder::init('greet {name=John^Doe} {--formal}');
        $builder->setArgument('name', '');

        $this->assertSame('~', $builder->getArgument('name'));
    }

    public function test_set_argument_with_numeric_value(): void
    {
        $builder = QueryBuilder::init('process {limit}');
        $builder->setArgument('limit', '42');

        $this->assertSame('42', $builder->getArgument('limit'));
    }

    public function test_build_with_multiple_flags(): void
    {
        $builder = QueryBuilder::init('deploy {--force} {--verbose} {--dry-run}');
        $builder->setFlag('--force', true);
        $builder->setFlag('--dry-run', true);

        $query = $builder->build();

        $this->assertSame('deploy --force --dry-run', $query);
    }

    public function test_build_with_multiple_arguments(): void
    {
        $builder = QueryBuilder::init('user-create {name} {email} {role=user}');
        $builder->setArgument('name', 'John');
        $builder->setArgument('email', 'john@example.com');
        $builder->setArgument('role', 'admin');

        $query = $builder->build();

        $this->assertSame('user-create John john@example.com admin', $query);
    }

    // ==================== TESTS: Real-world scenarios ====================

    public function test_task_process_tasks_signature(): void
    {
        $builder = QueryBuilder::init('process-tasks {format=text} {limit=?} {--unique-only} {--recurring-only} {--verbose}');

        $builder->setArgument('limit', '10');
        $builder->setArgument('format', 'json');
        $builder->setFlag('--unique-only', true);

        $query = $builder->build();

        $this->assertSame('process-tasks json 10 --unique-only', $query);
    }

    public function test_task_tasks_watch_signature(): void
    {
        $builder = QueryBuilder::init('tasks-watch {interval=60} {duration=?} {limit=?} {parallel=?} {--unique-only} {--recurring-only} {--verbose}');

        $builder->setArgument('interval', '30');
        $builder->setArgument('limit', '5');
        $builder->setFlag('--verbose', true);

        $query = $builder->build();

        $this->assertSame('tasks-watch 30 ~ 5 ~ --verbose', $query);
    }

    public function test_complete_deployment_signature(): void
    {
        $builder = QueryBuilder::init(
            'deploy {environment} {version} {--force} {--skip-tests} {--verbose}'
        );

        $builder->setArgument('environment', 'staging');
        $builder->setArgument('version', '1.2.3');
        $builder->setFlag('--force', true);
        $builder->setFlag('--skip-tests', true);

        $query = $builder->build();

        $this->assertSame('deploy staging 1.2.3 --force --skip-tests', $query);
    }

    public function test_complete_backup_signature(): void
    {
        $builder = QueryBuilder::init(
            'backup {source} {destination} {format=zip} {compression=6} {--force} {--verbose}'
        );

        $builder->setArgument('source', '/home/user/projects');
        $builder->setArgument('destination', '/backup');
        $builder->setArgument('format', 'tar.gz');
        $builder->setArgument('compression', '9');
        $builder->setFlag('--force', true);

        $query = $builder->build();

        $this->assertSame(
            'backup /home/user/projects /backup tar.gz 9 --force',
            $query
        );
    }

    // ==================== TESTS: Nullable specific ====================

    public function test_build_with_nullable_default_to_tilde(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?} {format=text}');

        $query = $builder->build();

        $this->assertSame('process-tasks ~ text', $query);
    }

    public function test_build_with_nullable_set_to_value(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?} {format=text}');
        $builder->setArgument('limit', '10');

        $query = $builder->build();

        $this->assertSame('process-tasks 10 text', $query);
    }

    public function test_build_with_multiple_nullables(): void
    {
        $builder = QueryBuilder::init('tasks-watch {duration=?} {limit=?} {parallel=?}');
        $builder->setArgument('duration', '30');

        $query = $builder->build();

        $this->assertSame('tasks-watch 30 ~ ~', $query);
    }

    public function test_set_argument_null_on_nullable_uses_tilde(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?}');
        $builder->setArgument('limit', null);

        $this->assertSame('~', $builder->getArgument('limit'));
    }

    public function test_set_argument_empty_on_nullable_uses_tilde(): void
    {
        $builder = QueryBuilder::init('process-tasks {limit=?}');
        $builder->setArgument('limit', '');

        $this->assertSame('~', $builder->getArgument('limit'));
    }

    public function test_set_required_on_default_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('backup {format=zip}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "format" is not a required argument in the signature');

        $builder->setRequired('format', 'tar');
    }

    public function test_set_default_on_required_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "name" is not a default argument in the signature');

        $builder->setDefault('name', 'John');
    }

    public function test_set_variadic_on_required_argument_throws_exception(): void
    {
        $builder = QueryBuilder::init('greet {name} {--formal}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument "name" is not a variadic argument in the signature');

        $builder->setVariadic('name', 'John');
    }

    public function test_set_custom_tag(): void
    {
        $builder = QueryBuilder::init('greet {name}');
        $builder->setRequired('name', 'John');
        $builder->setCustom('greeting', 'Hello World');

        $query = $builder->build();

        $this->assertSame('greet John <greeting="Hello World">', $query);
    }

    public function test_set_multiple_custom_tags(): void
    {
        $builder = QueryBuilder::init('send {recipient} {--verbose}');
        $builder->setRequired('recipient', 'John');
        $builder->setFlag('--verbose', true);
        $builder->setCustoms([
            'greeting' => 'Hello World',
            'later' => 'goodby',
        ]);

        $query = $builder->build();

        $this->assertSame('send John --verbose <greeting="Hello World"> <later="goodby">', $query);
    }

    public function test_remove_custom_tag(): void
    {
        $builder = QueryBuilder::init('greet {name}');
        $builder->setRequired('name', 'John');
        $builder->setCustom('greeting', 'Hello World');
        $builder->setCustom('later', 'goodby');
        $builder->removeCustom('later');

        $query = $builder->build();

        $this->assertSame('greet John <greeting="Hello World">', $query);
    }

    public function test_get_custom_tag(): void
    {
        $builder = QueryBuilder::init('greet {name}');
        $builder->setCustom('greeting', 'Hello World');

        $this->assertSame('Hello World', $builder->getCustom('greeting'));
        $this->assertNull($builder->getCustom('nonexistent'));
    }

    public function test_get_all_custom_tags(): void
    {
        $builder = QueryBuilder::init('greet {name}');
        $builder->setCustoms([
            'greeting' => 'Hello World',
            'later' => 'goodby',
        ]);

        $customs = $builder->getCustoms();

        $this->assertCount(2, $customs);
        $this->assertArrayHasKey('greeting', $customs);
        $this->assertArrayHasKey('later', $customs);
    }

    public function test_reset_clears_custom_tags(): void
    {
        $builder = QueryBuilder::init('greet {name}');
        $builder->setRequired('name', 'John');
        $builder->setCustom('greeting', 'Hello World');

        $builder->reset();

        $this->assertEmpty($builder->getCustoms());
        $this->assertNull($builder->getArgument('name'));
    }

    public function test_custom_tags_placed_at_end(): void
    {
        $builder = QueryBuilder::init('send {recipient} {--verbose}');
        $builder->setRequired('recipient', 'John');
        $builder->setFlag('--verbose', true);
        $builder->setCustom('greeting', 'Hello World');

        $query = $builder->build();

        // Custom tags must be at the end
        $this->assertSame('send John --verbose <greeting="Hello World">', $query);
    }
}
