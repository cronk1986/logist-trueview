<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\TicketStatus;

use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatus;
use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;

final class TicketStatusMother
{
    public static function create(int $id = 1, string $name = 'Open'): TicketStatus
    {
        return new TicketStatus(id: TicketStatusId::fromInt(value: $id), name: $name);
    }
}
