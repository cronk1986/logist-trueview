<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Logis\LogisContext\Application\ModelView\Location\LocationSearchResultView;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Search\NewestMatches;

final class EloquentLocationSearcher implements EntitySearcher
{
    public function entity(): SearchableEntity
    {
        return SearchableEntity::Locations;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $query = LocationModel::query()
            ->visibleTo($criteria->customers)
            ->matching($criteria->term);

        return NewestMatches::of(
            query: $query,
            limit: $criteria->limit,
            toRow: fn (LocationModel $location): array => $this->row(location: $location),
        );
    }

    /**
     * @return array{id: int, title: string, city: ?string, state: ?string, postal_code: ?string}
     */
    private function row(LocationModel $location): array
    {
        return LocationSearchResultView::from(
            id: $location->id,
            title: $location->title,
            city: $location->city,
            state: $location->state,
            postalCode: $location->postal_code,
        )->toArray();
    }
}
