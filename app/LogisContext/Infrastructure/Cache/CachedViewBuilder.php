<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

/**
 * @phpstan-import-type Shape from ViewPage
 */
final class CachedViewBuilder implements ViewBuilder
{
    private const KEY_PREFIX = 'views:v1';

    public function __construct(
        private readonly ViewBuilder $builder,
        private readonly Repository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    public function type(): ViewType
    {
        return $this->builder->type();
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        /** @var Shape $cached */
        $cached = $this->cache->remember(
            key: $this->key(criteria: $criteria),
            ttl: $this->ttlSeconds,
            callback: fn (): array => $this->builder->execute(criteria: $criteria)->toArray(),
        );

        return ViewPage::fromArray(values: $cached);
    }

    private function key(ViewExecutionCriteria $criteria): string
    {
        $fingerprint = json_encode(value: [
            'customer' => $criteria->customers->customer()?->value(),
            'sub_accounts' => $criteria->customers->includesSubAccounts(),
            'columns' => $criteria->definition->columns()->toArray(),
            'filters' => $criteria->definition->filters()->toArray(),
            'sort' => [$criteria->sort->field(), $criteria->sort->direction()->value],
            'term' => $this->term(criteria: $criteria),
            'page_size' => $criteria->pageSize->value(),
            'cursor' => $criteria->cursor,
        ], flags: JSON_THROW_ON_ERROR);

        return implode(separator: ':', array: [
            self::KEY_PREFIX,
            $this->type()->value,
            hash(algo: 'sha256', data: $fingerprint),
        ]);
    }

    private function term(ViewExecutionCriteria $criteria): ?string
    {
        if ($criteria->term === null) {
            return null;
        }

        return mb_strtolower(string: $criteria->term->value());
    }
}
