<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Logis\LogisContext\Application\ModelView\Asset\AssetSearchResultView;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Search\NewestMatches;

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

        return NewestMatches::of(
            query: $query,
            limit: $criteria->limit,
            toRow: fn (AssetModel $asset): array => $this->row(asset: $asset),
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
