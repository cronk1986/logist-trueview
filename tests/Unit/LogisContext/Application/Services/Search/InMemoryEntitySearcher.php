<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\Search;

use Logis\LogisContext\Application\Services\Search\EntitySearcher;
use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use Logis\LogisContext\Application\Services\Search\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class InMemoryEntitySearcher implements EntitySearcher
{
    /** @var list<SearchCriteria> */
    private array $calls = [];

    public function __construct(
        private readonly SearchableEntity $entity,
        private readonly EntitySearchResult $result,
    ) {
    }

    public static function returningNothing(SearchableEntity $entity): self
    {
        return new self(entity: $entity, result: EntitySearchResult::empty());
    }

    public function entity(): SearchableEntity
    {
        return $this->entity;
    }

    public function search(SearchCriteria $criteria): EntitySearchResult
    {
        $this->calls[] = $criteria;

        return $this->result;
    }

    /**
     * @return list<SearchCriteria>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
