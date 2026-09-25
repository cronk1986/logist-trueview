<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Logis\LogisContext\Application\ModelView\Contract\ContractSearchResultView;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

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

        $total = $query->clone()->count();

        if ($total === 0) {
            return EntitySearchResult::empty();
        }

        $contracts = $query
            ->orderByDesc('id')
            ->limit($criteria->limit->value())
            ->get();

        return EntitySearchResult::from(
            results: array_values(array: $contracts->map(callback: fn (ContractModel $contract): array => $this->row(contract: $contract))->all()),
            total: $total,
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
