<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\FieldMapping;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;

final class EloquentTicketViewBuilder implements ViewBuilder
{
    public function __construct(private readonly ViewPaginator $paginator)
    {
    }

    public function type(): ViewType
    {
        return ViewType::MyTickets;
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        $query = TicketModel::query()->visibleTo($criteria->customers);

        if ($criteria->term !== null) {
            $query->matching($criteria->term);
        }

        return $this->paginator->paginate(query: $query, fields: self::fields(), criteria: $criteria);
    }

    /**
     * @return array<string, FieldMapping>
     */
    public static function fields(): array
    {
        return [
            'id' => FieldMapping::toColumn(column: 'id'),
            'repair_id' => FieldMapping::toColumn(column: 'repair_id'),
            'incident_number' => FieldMapping::toColumn(column: 'incident_number'),
            'subject' => FieldMapping::toColumn(column: 'subject'),
            'status' => FieldMapping::toRelation(relation: 'status', foreignKey: 'status_id', label: 'name'),
            'asset' => FieldMapping::toRelation(relation: 'asset', foreignKey: 'asset_id', label: 'serial_number'),
            'customer' => FieldMapping::toRelation(relation: 'customer', foreignKey: 'customer_id', label: 'name'),
            'created_at' => FieldMapping::toColumn(column: 'created_at'),
            'updated_at' => FieldMapping::toColumn(column: 'updated_at'),
        ];
    }
}
