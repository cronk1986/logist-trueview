<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Logis\LogisContext\Domain\Model\Ticket\Ticket;
use Logis\LogisContext\Domain\Model\Ticket\TicketRepository;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;

final class EloquentTicketRepository implements TicketRepository
{
    public function __construct(private readonly TicketMapper $mapper)
    {
    }

    public function findById(TicketId $id): ?Ticket
    {
        $model = TicketModel::query()->with(relations: TicketMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
