<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\Search;

use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use Logis\LogisContext\Application\Services\Search\SearchCriteria;
use Logis\LogisContext\Application\Services\Search\EntitySearchers;
use Logis\LogisContext\Application\ModelView\Search\EntitySearchResultView;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\QueryResponse\Meta;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class SearchServiceHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntitySearchers $searchers,
    ) {
    }

    public function handle(SearchService $query): QueryResponse
    {
        $user = $this->users->findById(id: UserId::fromInt(value: $query->userId));

        if ($user === null) {
            throw UserNotFoundException::withId(id: $query->userId);
        }

        $criteria = new SearchCriteria(
            term: SearchTerm::fromString(value: $query->term),
            limit: SearchLimit::fromInt(value: $query->limit),
            customers: $user->visibleCustomers(),
        );

        $data = [];

        foreach ($this->requestedEntities(query: $query) as $entity) {
            $data[$entity->value] = EntitySearchResultView::from(
                result: $this->search(entity: $entity, criteria: $criteria),
                limit: $criteria->limit,
            )->toArray();
        }

        return QueryResponse::from(data: $data, meta: Meta::from(values: [
            'query' => $criteria->term->value(),
            'limit_per_entity' => $criteria->limit->value(),
        ]));
    }

    /**
     * @return list<SearchableEntity>
     */
    private function requestedEntities(SearchService $query): array
    {
        $entities = [];

        foreach (SearchableEntity::cases() as $entity) {
            if (in_array(needle: $entity->value, haystack: $query->entities, strict: true)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    private function search(SearchableEntity $entity, SearchCriteria $criteria): EntitySearchResult
    {
        if ($criteria->customers->isEmpty()) {
            return EntitySearchResult::empty();
        }

        return $this->searchers->for(entity: $entity)->search(criteria: $criteria);
    }
}
