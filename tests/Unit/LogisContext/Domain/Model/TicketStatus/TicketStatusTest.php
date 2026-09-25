<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\TicketStatus;

use PHPUnit\Framework\TestCase;

final class TicketStatusTest extends TestCase
{
    public function test_it_exposes_its_id_and_name(): void
    {
        $status = TicketStatusMother::create(id: 2, name: 'In Progress');

        $this->assertSame(2, $status->id()->value());
        $this->assertSame('In Progress', $status->name());
    }
}
