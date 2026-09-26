<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Logis\LogisContext\Application\ModelView\Ticket\TicketSearchResultView;
use Logis\LogisContext\Application\Services\Search\EntitySearcher;
use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use Logis\LogisContext\Application\Services\Search\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Search\NewestMatches;

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
            ->matching($criteria->term)
            ->with(relations: 'status');

        return NewestMatches::of(
            query: $query,
            limit: $criteria->limit,
            toRow: fn (TicketModel $ticket): array => $this->row(ticket: $ticket),
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
