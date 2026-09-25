<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Logis\LogisContext\Application\ModelView\Asset\AssetSearchResultView;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class EloquentAssetSearcher implements EntitySearcher
{
    public function entity(): SearchableEntity
    {
        return SearchableEntity::Assets;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $query = AssetModel::query()
            ->visibleTo($criteria->customers)
            ->matching($criteria->term);

        $total = $query->clone()->count();

        if ($total === 0) {
            return EntitySearchResult::empty();
        }

        $assets = $query
            ->orderByDesc('id')
            ->limit($criteria->limit->value())
            ->get();

        return EntitySearchResult::from(
            results: array_values(array: $assets->map(callback: fn (AssetModel $asset): array => $this->row(asset: $asset))->all()),
            total: $total,
        );
    }

    /**
     * @return array{id: int, serial_number: string, manufacturer: string, model: string, item_name: string, status: string}
     */
    private function row(AssetModel $asset): array
    {
        return AssetSearchResultView::from(
            id: $asset->id,
            serialNumber: $asset->serial_number,
            manufacturer: $asset->manufacturer,
            model: $asset->model,
            itemName: $asset->item_name,
            status: $asset->status->value,
        )->toArray();
    }
}
