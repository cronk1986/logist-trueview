<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\TicketStatus;

use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;

final class TicketStatus
{
    public function __construct(
        private readonly TicketStatusId $id,
        private readonly string $name,
    ) {
    }

    public function id(): TicketStatusId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
