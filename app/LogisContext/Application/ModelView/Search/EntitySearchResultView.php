<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\Search;

use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;

final readonly class EntitySearchResultView
{
    /**
     * @param list<array<string, scalar|null>> $results
     */
    private function __construct(public array $results, public int $total, public bool $hasMore)
    {
    }

    public static function from(EntitySearchResult $result, SearchLimit $limit): self
    {
        return new self(
            results: $result->results(),
            total: $result->total(),
            hasMore: $limit->isExceededBy(total: $result->total()),
        );
    }

    /**
     * @return array{results: list<array<string, scalar|null>>, total: int, has_more: bool}
     */
    public function toArray(): array
    {
        return [
            'results' => $this->results,
            'total' => $this->total,
            'has_more' => $this->hasMore,
        ];
    }
}
