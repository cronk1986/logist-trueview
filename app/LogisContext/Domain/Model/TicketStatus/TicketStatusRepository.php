<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\TicketStatus;

use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;

interface TicketStatusRepository
{
    public function findById(TicketStatusId $id): ?TicketStatus;
}
