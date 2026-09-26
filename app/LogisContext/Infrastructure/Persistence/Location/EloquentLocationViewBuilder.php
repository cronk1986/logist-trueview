<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\FieldMapping;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;

final class EloquentLocationViewBuilder implements ViewBuilder
{
    public function __construct(private readonly ViewPaginator $paginator)
    {
    }

    public function type(): ViewType
    {
        return ViewType::MyLocations;
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        $query = LocationModel::query()->visibleTo($criteria->customers);

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
            'title' => FieldMapping::toColumn(column: 'title'),
            'full_address' => FieldMapping::toColumn(column: 'full_address'),
            'city' => FieldMapping::toColumn(column: 'city'),
            'state' => FieldMapping::toColumn(column: 'state'),
            'postal_code' => FieldMapping::toColumn(column: 'postal_code'),
            'customer' => FieldMapping::toRelation(relation: 'customer', foreignKey: 'customer_id', label: 'name'),
            'created_at' => FieldMapping::toColumn(column: 'created_at'),
            'updated_at' => FieldMapping::toColumn(column: 'updated_at'),
        ];
    }
}
