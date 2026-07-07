<?php

declare(strict_types=1);

namespace AndyDefer\SignatureParser\Tests\Unit\Formatters;

use AndyDefer\SignatureParser\Formatters\TextFormatter;
use PHPUnit\Framework\TestCase;

final class TextFormatterTest extends TestCase
{
    public function test_format_simple_array(): void
    {
        // Arrange
        $data = [
            'name' => 'John^Doe',
            'email' => 'john@example.com',
            'city' => 'New^York^City',
        ];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('New York City', $result['city']);
    }

    public function test_format_nested_array(): void
    {
        // Arrange
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

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('John Michael', $result['user']['first_name']);
        $this->assertSame('Doe Smith', $result['user']['last_name']);
        $this->assertSame('123 Main Street', $result['user']['address']['street']);
        $this->assertSame('Los Angeles', $result['user']['address']['city']);
        $this->assertSame('90210', $result['user']['address']['zip']);
        $this->assertSame('Hello World! How are you?', $result['message']);
    }

    public function test_format_array_with_scalar_values(): void
    {
        // Arrange
        $data = [
            'name' => 'John^Doe',
            'age' => 30,
            'active' => true,
            'score' => 95.5,
            'tags' => ['PHP^8.4', 'Laravel', 'Vue^js'],
        ];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('John Doe', $result['name']);
        $this->assertSame(30, $result['age']);
        $this->assertTrue($result['active']);
        $this->assertSame(95.5, $result['score']);
        $this->assertSame(['PHP 8.4', 'Laravel', 'Vue js'], $result['tags']);
    }

    public function test_format_array_with_null_values(): void
    {
        // Arrange
        $data = [
            'name' => 'John^Doe',
            'email' => null,
            'address' => [
                'street' => '123^Main^Street',
                'city' => null,
            ],
        ];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('John Doe', $result['name']);
        $this->assertNull($result['email']);
        $this->assertSame('123 Main Street', $result['address']['street']);
        $this->assertNull($result['address']['city']);
    }

    public function test_format_deeply_nested_array(): void
    {
        // Arrange
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

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('Hello World! This is a deep message', $result['level1']['level2']['level3']['level4']['message']);
        $this->assertSame(['item 1', 'item 2'], $result['level1']['level2']['level3']['items']);
        $this->assertSame('PHP 8.4', $result['level1']['level2']['tag']);
        $this->assertSame('John Doe', $result['level1']['name']);
    }

    public function test_format_string_single_value(): void
    {
        // Arrange
        $value = 'Hello^World!^How^are^you?';

        // Act
        $result = TextFormatter::formatString($value);

        // Assert
        $this->assertSame('Hello World! How are you?', $result);
    }

    public function test_format_string_no_caret(): void
    {
        // Arrange
        $value = 'Hello World!';

        // Act
        $result = TextFormatter::formatString($value);

        // Assert
        $this->assertSame('Hello World!', $result);
    }

    public function test_format_string_with_multiple_carets(): void
    {
        // Arrange
        $value = 'This^is^a^test^with^multiple^carets';

        // Act
        $result = TextFormatter::formatString($value);

        // Assert
        $this->assertSame('This is a test with multiple carets', $result);
    }

    public function test_format_array_with_mixed_types(): void
    {
        // Arrange
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

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('Hello World', $result['string']);
        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertFalse($result['bool']);
        $this->assertNull($result['null']);
        $this->assertSame('Nested Value', $result['array']['nested_string']);
        $this->assertSame(100, $result['array']['nested_int']);
        $this->assertSame(['Item 1', 'Item 2', 'Item 3'], $result['list']);
    }

    public function test_format_empty_array(): void
    {
        // Arrange
        $data = [];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame([], $result);
    }

    public function test_format_array_with_carets(): void
    {
        // Arrange
        $data = [
            'three_carets' => '^^^',
            'empty' => '',
            'carets_with_spaces' => '^ ^ ^',
        ];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('   ', $result['three_carets']);
        $this->assertSame('', $result['empty']);
        $this->assertSame('     ', $result['carets_with_spaces']);
    }

    public function test_format_array_with_caret_and_text(): void
    {
        // Arrange
        $data = [
            'text_with_carets' => 'Hello^^World',
            'text_with_carets_and_spaces' => 'Hello^ ^World',
        ];

        // Act
        $result = TextFormatter::format($data);

        // Assert
        $this->assertSame('Hello  World', $result['text_with_carets']);
        $this->assertSame('Hello   World', $result['text_with_carets_and_spaces']);
    }
}
