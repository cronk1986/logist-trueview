<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Customer;

use Logis\LogisContext\Domain\Exception\Customer\InvalidErpIdException;
use Logis\LogisContext\Domain\ValueObject\Customer\ErpId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErpIdTest extends TestCase
{
    public function test_it_keeps_a_valid_value_without_surrounding_spaces(): void
    {
        $this->assertSame('C-1000', ErpId::fromString(value: '  C-1000 ')->value());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_an_invalid_value(string $value): void
    {
        $this->expectException(InvalidErpIdException::class);

        ErpId::fromString(value: $value);
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
        $this->assertTrue(ErpId::fromString(value: 'C-1000')->equals(other: ErpId::fromString(value: 'C-1000')));
        $this->assertFalse(ErpId::fromString(value: 'C-1000')->equals(other: ErpId::fromString(value: 'other')));
    }
}
