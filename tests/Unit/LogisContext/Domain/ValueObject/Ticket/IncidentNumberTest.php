<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Ticket;

use Logis\LogisContext\Domain\Exception\Ticket\InvalidIncidentNumberException;
use Logis\LogisContext\Domain\ValueObject\Ticket\IncidentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IncidentNumberTest extends TestCase
{
    public function test_it_keeps_a_valid_value_without_surrounding_spaces(): void
    {
        $this->assertSame('INC-2001', IncidentNumber::fromString(value: '  INC-2001 ')->value());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_an_invalid_value(string $value): void
    {
        $this->expectException(InvalidIncidentNumberException::class);

        IncidentNumber::fromString(value: $value);
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
        $this->assertTrue(IncidentNumber::fromString(value: 'INC-2001')->equals(other: IncidentNumber::fromString(value: 'INC-2001')));
        $this->assertFalse(IncidentNumber::fromString(value: 'INC-2001')->equals(other: IncidentNumber::fromString(value: 'other')));
    }
}
