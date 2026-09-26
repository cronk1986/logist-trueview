<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\Search;

use Logis\LogisContext\Application\Exception\Search\EntitySearcherNotRegisteredException;
use Logis\LogisContext\Application\Services\Search\EntitySearchers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use PHPUnit\Framework\TestCase;

final class EntitySearchersTest extends TestCase
{
    public function test_it_resolves_the_searcher_of_every_entity(): void
    {
        $searchers = array_map(
            callback: fn (SearchableEntity $entity): InMemoryEntitySearcher => InMemoryEntitySearcher::returningNothing(entity: $entity),
            array: SearchableEntity::cases(),
        );

        $registry = new EntitySearchers(searchers: $searchers);

        foreach (SearchableEntity::cases() as $entity) {
            $this->assertSame($entity, $registry->for(entity: $entity)->entity());
        }
    }

    public function test_every_entity_needs_a_searcher(): void
    {
        $this->expectException(EntitySearcherNotRegisteredException::class);
        $this->expectExceptionMessage('No searcher is registered for "tickets".');

        new EntitySearchers(searchers: []);
    }

    public function test_an_entity_cannot_have_two_searchers(): void
    {
        $this->expectException(EntitySearcherNotRegisteredException::class);
        $this->expectExceptionMessage('More than one searcher is registered for "tickets".');

        new EntitySearchers(searchers: [
            InMemoryEntitySearcher::returningNothing(entity: SearchableEntity::Tickets),
            InMemoryEntitySearcher::returningNothing(entity: SearchableEntity::Tickets),
        ]);
    }
}
