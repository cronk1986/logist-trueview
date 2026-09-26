<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\Search;

use Logis\LogisContext\Application\Exception\Search\EntitySearcherNotRegisteredException;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class EntitySearchers
{
    /** @var array<string, EntitySearcher> */
    private array $searchers = [];

    /**
     * @param iterable<EntitySearcher> $searchers
     */
    public function __construct(iterable $searchers)
    {
        foreach ($searchers as $searcher) {
            $this->register(searcher: $searcher);
        }

        $this->ensureEveryEntityHasASearcher();
    }

    public function for(SearchableEntity $entity): EntitySearcher
    {
        return $this->searchers[$entity->value];
    }

    private function register(EntitySearcher $searcher): void
    {
        $entity = $searcher->entity();

        if (array_key_exists(key: $entity->value, array: $this->searchers)) {
            throw EntitySearcherNotRegisteredException::registeredTwice(entity: $entity);
        }

        $this->searchers[$entity->value] = $searcher;
    }

    private function ensureEveryEntityHasASearcher(): void
    {
        foreach (SearchableEntity::cases() as $entity) {
            if (!array_key_exists(key: $entity->value, array: $this->searchers)) {
                throw EntitySearcherNotRegisteredException::forEntity(entity: $entity);
            }
        }
    }
}
