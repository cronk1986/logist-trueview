<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Ticket;

use Logis\LogisContext\Domain\Exception\Ticket\InvalidTicketIdException;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;
use PHPUnit\Framework\TestCase;

final class TicketIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, TicketId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidTicketIdException::class);

        TicketId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(TicketId::fromInt(value: 3)->equals(other: TicketId::fromInt(value: 3)));
        $this->assertFalse(TicketId::fromInt(value: 3)->equals(other: TicketId::fromInt(value: 4)));
    }
}
