<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Logis\LogisContext\Application\ModelView\Ticket\TicketSearchResultView;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class EloquentTicketSearcher implements EntitySearcher
{
    public function entity(): SearchableEntity
    {
        return SearchableEntity::Tickets;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $query = TicketModel::query()
            ->visibleTo($criteria->customers)
            ->matching($criteria->term);

        $total = $query->clone()->count();

        if ($total === 0) {
            return EntitySearchResult::empty();
        }

        $tickets = $query
            ->with(relations: 'status')
            ->orderByDesc('id')
            ->limit($criteria->limit->value())
            ->get();

        return EntitySearchResult::from(
            results: array_values(array: $tickets->map(callback: fn (TicketModel $ticket): array => $this->row(ticket: $ticket))->all()),
            total: $total,
        );
    }

    /**
     * @return array{id: int, repair_id: string, incident_number: string, subject: string, status: string}
     */
    private function row(TicketModel $ticket): array
    {
        return TicketSearchResultView::from(
            id: $ticket->id,
            repairId: $ticket->repair_id,
            incidentNumber: $ticket->incident_number,
            subject: $ticket->subject,
            status: $ticket->status->name,
        )->toArray();
    }
}
