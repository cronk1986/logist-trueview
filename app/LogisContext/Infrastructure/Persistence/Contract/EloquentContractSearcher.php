<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Logis\LogisContext\Application\ModelView\Contract\ContractSearchResultView;
use Logis\LogisContext\Application\Services\Search\EntitySearcher;
use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use Logis\LogisContext\Application\Services\Search\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Search\NewestMatches;

final class EloquentContractSearcher implements EntitySearcher
{
    public function entity(): SearchableEntity
    {
        return SearchableEntity::Contracts;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $query = ContractModel::query()
            ->visibleTo($criteria->customers)
            ->active()
            ->matching($criteria->term);

        return NewestMatches::of(
            query: $query,
            limit: $criteria->limit,
            toRow: fn (ContractModel $contract): array => $this->row(contract: $contract),
        );
    }

    /**
     * @return array{id: int, name: string, status: string}
     */
    private function row(ContractModel $contract): array
    {
        return ContractSearchResultView::from(
            id: $contract->id,
            name: $contract->name,
            status: $contract->status->value,
        )->toArray();
    }
}
