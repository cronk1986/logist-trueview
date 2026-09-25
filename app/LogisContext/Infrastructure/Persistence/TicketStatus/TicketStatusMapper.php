<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\TicketStatus;

use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatus;
use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;

final class TicketStatusMapper
{
    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return [];
    }

    public function toDomain(TicketStatusModel $model): TicketStatus
    {
        return new TicketStatus(id: TicketStatusId::fromInt(value: $model->id), name: $model->name);
    }
}
