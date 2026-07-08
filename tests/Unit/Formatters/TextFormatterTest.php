<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Formatters;

use AndyDefer\SignatureParser\Formatters\TextFormatter;
use PHPUnit\Framework\TestCase;

final class TextFormatterTest extends TestCase
{
    // ==================== BASIC CARET REPLACEMENT TESTS ====================

    public function test_replaces_caret_with_space_in_simple_array(): void
    {
        $data = [
            'name' => 'John^Doe',
            'email' => 'john@example.com',
            'city' => 'New^York^City',
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('New York City', $result['city']);
    }

    public function test_replaces_caret_in_nested_arrays(): void
    {
        $data = [
            'user' => [
                'first_name' => 'John^Michael',
                'last_name' => 'Doe^Smith',
                'address' => [
                    'street' => '123^Main^Street',
                    'city' => 'Los^Angeles',
                    'zip' => '90210',
                ],
            ],
            'message' => 'Hello^World!^How^are^you?',
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('John Michael', $result['user']['first_name']);
        $this->assertSame('Doe Smith', $result['user']['last_name']);
        $this->assertSame('123 Main Street', $result['user']['address']['street']);
        $this->assertSame('Los Angeles', $result['user']['address']['city']);
        $this->assertSame('90210', $result['user']['address']['zip']);
        $this->assertSame('Hello World! How are you?', $result['message']);
    }

    public function test_replaces_caret_in_deeply_nested_arrays(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'message' => 'Hello^World!^This^is^a^deep^message',
                        ],
                        'items' => ['item^1', 'item^2'],
                    ],
                    'tag' => 'PHP^8.4',
                ],
                'name' => 'John^Doe',
            ],
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('Hello World! This is a deep message', $result['level1']['level2']['level3']['level4']['message']);
        $this->assertSame(['item 1', 'item 2'], $result['level1']['level2']['level3']['items']);
        $this->assertSame('PHP 8.4', $result['level1']['level2']['tag']);
        $this->assertSame('John Doe', $result['level1']['name']);
    }

    public function test_replaces_caret_with_multiple_carets(): void
    {
        $value = 'This^is^a^test^with^multiple^carets';

        $result = TextFormatter::formatString($value);

        $this->assertSame('This is a test with multiple carets', $result);
    }

    public function test_replaces_caret_with_spaces_and_handles_extra_spaces(): void
    {
        $data = [
            'three_carets' => '^^^',
            'empty' => '',
            'carets_with_spaces' => '^ ^ ^',
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('   ', $result['three_carets']);
        $this->assertSame('', $result['empty']);
        $this->assertSame('     ', $result['carets_with_spaces']);
    }

    public function test_replaces_caret_with_adjacent_characters(): void
    {
        $data = [
            'text_with_carets' => 'Hello^^World',
            'text_with_carets_and_spaces' => 'Hello^ ^World',
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('Hello  World', $result['text_with_carets']);
        $this->assertSame('Hello   World', $result['text_with_carets_and_spaces']);
    }

    // ==================== SPECIAL TOKENS TESTS ====================

    public function test_handles_question_mark_as_null(): void
    {
        $data = ['value' => '?'];

        $result = TextFormatter::format($data);

        $this->assertNull($result['value']);
    }

    public function test_handles_tilde_as_null(): void
    {
        $data = ['value' => '~'];

        $result = TextFormatter::format($data);

        $this->assertNull($result['value']);
    }

    public function test_escapes_double_question_mark(): void
    {
        $data = ['value' => '??'];

        $result = TextFormatter::format($data);

        $this->assertSame('?', $result['value']);
    }

    public function test_escapes_double_tilde(): void
    {
        $data = ['value' => '~~'];

        $result = TextFormatter::format($data);

        $this->assertSame('~', $result['value']);
    }

    public function test_format_string_handles_all_special_tokens(): void
    {
        $this->assertNull(TextFormatter::formatString('?'));
        $this->assertNull(TextFormatter::formatString('~'));
        $this->assertSame('?', TextFormatter::formatString('??'));
        $this->assertSame('~', TextFormatter::formatString('~~'));
        $this->assertSame('Hello World', TextFormatter::formatString('Hello^World'));
        $this->assertSame('normal string', TextFormatter::formatString('normal string'));
        $this->assertSame('', TextFormatter::formatString(''));
        $this->assertSame('  ', TextFormatter::formatString('^^'));
        $this->assertSame('   ', TextFormatter::formatString('^ ^'));
    }

    public function test_handles_mixed_special_tokens_in_arrays(): void
    {
        $data = [
            'name' => 'John^Doe',
            'age' => 30,
            'active' => true,
            'tags' => ['PHP^8.4', '??', '~'],
            'config' => [
                'env' => '?',
                'debug' => false,
                'skip' => '~~',
            ],
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame(30, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame(['PHP 8.4', '?', null], $result['tags']);
        $this->assertNull($result['config']['env']);
        $this->assertFalse($result['config']['debug']);
        $this->assertSame('~', $result['config']['skip']);
    }

    // ==================== REAL-WORLD EXAMPLES ====================

    public function test_real_world_command_example(): void
    {
        $data = [
            'source' => '/home/user/My^Project',
            'destination' => '/backup',
            'format' => 'tar.gz',
            'excludes' => ['cache^folder', 'logs^folder'],
            'env' => '?',
            'verbose' => '~',
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('/home/user/My Project', $result['source']);
        $this->assertSame('/backup', $result['destination']);
        $this->assertSame('tar.gz', $result['format']);
        $this->assertSame(['cache folder', 'logs folder'], $result['excludes']);
        $this->assertNull($result['env']);
        $this->assertNull($result['verbose']);
    }

    // ==================== EDGE CASES ====================

    public function test_preserves_non_string_values(): void
    {
        $data = [
            'string' => 'Hello^World',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('Hello World', $result['string']);
        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertTrue($result['bool']);
        $this->assertNull($result['null']);
    }

    public function test_handles_array_with_null_values(): void
    {
        $data = [
            'name' => 'John^Doe',
            'email' => null,
            'address' => [
                'street' => '123^Main^Street',
                'city' => null,
            ],
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('John Doe', $result['name']);
        $this->assertNull($result['email']);
        $this->assertSame('123 Main Street', $result['address']['street']);
        $this->assertNull($result['address']['city']);
    }

    public function test_handles_empty_array(): void
    {
        $result = TextFormatter::format([]);

        $this->assertSame([], $result);
    }

    public function test_preserves_array_keys(): void
    {
        $data = [
            'first' => 'John^Doe',
            'second' => '?',
            'third' => '??',
            'fourth' => '~',
            'fifth' => '~~',
        ];

        $result = TextFormatter::format($data);

        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('second', $result);
        $this->assertArrayHasKey('third', $result);
        $this->assertArrayHasKey('fourth', $result);
        $this->assertArrayHasKey('fifth', $result);
    }

    public function test_handles_no_caret_in_string(): void
    {
        $value = 'Hello World!';

        $result = TextFormatter::formatString($value);

        $this->assertSame('Hello World!', $result);
    }

    public function test_handles_mixed_scalar_types(): void
    {
        $data = [
            'string' => 'Hello^World',
            'int' => 42,
            'float' => 3.14,
            'bool' => false,
            'null' => null,
            'array' => [
                'nested_string' => 'Nested^Value',
                'nested_int' => 100,
            ],
            'list' => ['Item^1', 'Item^2', 'Item^3'],
        ];

        $result = TextFormatter::format($data);

        $this->assertSame('Hello World', $result['string']);
        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertFalse($result['bool']);
        $this->assertNull($result['null']);
        $this->assertSame('Nested Value', $result['array']['nested_string']);
        $this->assertSame(100, $result['array']['nested_int']);
        $this->assertSame(['Item 1', 'Item 2', 'Item 3'], $result['list']);
    }
}
