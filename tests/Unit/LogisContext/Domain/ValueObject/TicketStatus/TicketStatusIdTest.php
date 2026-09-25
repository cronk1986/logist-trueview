<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\TicketStatus;

use Logis\LogisContext\Domain\Exception\TicketStatus\InvalidTicketStatusIdException;
use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;
use PHPUnit\Framework\TestCase;

final class TicketStatusIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, TicketStatusId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidTicketStatusIdException::class);

        TicketStatusId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(TicketStatusId::fromInt(value: 3)->equals(other: TicketStatusId::fromInt(value: 3)));
        $this->assertFalse(TicketStatusId::fromInt(value: 3)->equals(other: TicketStatusId::fromInt(value: 4)));
    }
}
