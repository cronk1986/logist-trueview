<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\FieldMapping;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;

final class EloquentAssetViewBuilder implements ViewBuilder
{
    public function __construct(private readonly ViewPaginator $paginator)
    {
    }

    public function type(): ViewType
    {
        return ViewType::MyAssets;
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        $query = AssetModel::query()->visibleTo($criteria->customers);

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
            'serial_number' => FieldMapping::toColumn(column: 'serial_number'),
            'manufacturer' => FieldMapping::toColumn(column: 'manufacturer'),
            'model' => FieldMapping::toColumn(column: 'model'),
            'item_name' => FieldMapping::toColumn(column: 'item_name'),
            'status' => FieldMapping::toColumn(column: 'status'),
            'customer' => FieldMapping::toRelation(relation: 'customer', foreignKey: 'customer_id', label: 'name'),
            'contract' => FieldMapping::toRelation(relation: 'contract', foreignKey: 'contract_id', label: 'name'),
            'location' => FieldMapping::toRelation(relation: 'location', foreignKey: 'location_id', label: 'title'),
            'created_at' => FieldMapping::toColumn(column: 'created_at'),
            'updated_at' => FieldMapping::toColumn(column: 'updated_at'),
        ];
    }
}
