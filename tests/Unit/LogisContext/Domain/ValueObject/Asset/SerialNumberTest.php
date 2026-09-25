<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Asset;

use Logis\LogisContext\Domain\Exception\Asset\InvalidSerialNumberException;
use Logis\LogisContext\Domain\ValueObject\Asset\SerialNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SerialNumberTest extends TestCase
{
    public function test_it_keeps_a_valid_value_without_surrounding_spaces(): void
    {
        $this->assertSame('SN-000123', SerialNumber::fromString(value: '  SN-000123 ')->value());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_an_invalid_value(string $value): void
    {
        $this->expectException(InvalidSerialNumberException::class);

        SerialNumber::fromString(value: $value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'only spaces' => ['   '],
            'too long' => [str_repeat(string: 'a', times: 256)],
        ];
    }

    public function test_two_values_with_the_same_content_are_equal(): void
    {
        $this->assertTrue(SerialNumber::fromString(value: 'SN-000123')->equals(other: SerialNumber::fromString(value: 'SN-000123')));
        $this->assertFalse(SerialNumber::fromString(value: 'SN-000123')->equals(other: SerialNumber::fromString(value: 'other')));
    }
}
