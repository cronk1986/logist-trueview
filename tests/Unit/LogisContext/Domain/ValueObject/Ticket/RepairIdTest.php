<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Ticket;

use Logis\LogisContext\Domain\Exception\Ticket\InvalidRepairIdException;
use Logis\LogisContext\Domain\ValueObject\Ticket\RepairId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepairIdTest extends TestCase
{
    public function test_it_keeps_a_valid_value_without_surrounding_spaces(): void
    {
        $this->assertSame('RMA-1001', RepairId::fromString(value: '  RMA-1001 ')->value());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_an_invalid_value(string $value): void
    {
        $this->expectException(InvalidRepairIdException::class);

        RepairId::fromString(value: $value);
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
        $this->assertTrue(RepairId::fromString(value: 'RMA-1001')->equals(other: RepairId::fromString(value: 'RMA-1001')));
        $this->assertFalse(RepairId::fromString(value: 'RMA-1001')->equals(other: RepairId::fromString(value: 'other')));
    }
}
