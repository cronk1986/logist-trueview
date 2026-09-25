<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\TicketStatus;

use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatus;
use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatusRepository;
use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;

final class EloquentTicketStatusRepository implements TicketStatusRepository
{
    public function __construct(private readonly TicketStatusMapper $mapper)
    {
    }

    public function findById(TicketStatusId $id): ?TicketStatus
    {
        $model = TicketStatusModel::query()->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
