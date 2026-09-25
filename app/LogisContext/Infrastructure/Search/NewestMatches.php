<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;

final class NewestMatches
{
    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     * @param Closure(TModel): array<string, scalar|null> $toRow
     */
    public static function of(Builder $query, SearchLimit $limit, Closure $toRow): EntitySearchResult
    {
        $total = $query->clone()->count();

        if ($total === 0) {
            return EntitySearchResult::empty();
        }

        $models = $query
            ->orderByDesc('id')
            ->limit($limit->value())
            ->get();

        return EntitySearchResult::from(results: array_values(array: $models->map(callback: $toRow)->all()), total: $total);
    }
}
