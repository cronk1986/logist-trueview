<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearcher;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class CachedEntitySearcher implements EntitySearcher
{
    private const KEY_PREFIX = 'search:v1';

    public function __construct(
        private readonly EntitySearcher $searcher,
        private readonly Repository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function entity(): SearchableEntity
    {
        return $this->searcher->entity();
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        /** @var array{results: list<array<string, scalar|null>>, total: int} $cached */
        $cached = $this->cache->remember(
            key: $this->key(criteria: $criteria),
            ttl: $this->ttlSeconds,
            callback: fn (): array => $this->searcher->search(criteria: $criteria)->toArray(),
        );

        return EntitySearchResult::fromArray(values: $cached);
    }

    private function key(SearchCriteria $criteria): string
    {
        $fingerprint = implode(separator: '|', array: [
            implode(separator: ',', array: $criteria->customers->values()),
            mb_strtolower(string: $criteria->term->value()),
            $criteria->limit->value(),
        ]);

        return implode(separator: ':', array: [
            self::KEY_PREFIX,
            $this->entity()->value,
            hash(algo: 'sha256', data: $fingerprint),
        ]);
    }
}
