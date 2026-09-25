<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Ticket;

use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;

interface TicketRepository
{
    public function findById(TicketId $id): ?Ticket;
}
