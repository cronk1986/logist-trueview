<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Providers;

use Logis\LogisContext\Application\Services\Search\EntitySearchers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Cache\CachedEntitySearcher;
use Tests\TestCase;

final class LogisServiceProviderTest extends TestCase
{
    public function test_every_searchable_entity_has_a_cached_searcher_in_the_container(): void
    {
        $searchers = $this->app->make(abstract: EntitySearchers::class);

        foreach (SearchableEntity::cases() as $entity) {
            $searcher = $searchers->for(entity: $entity);

            $this->assertInstanceOf(CachedEntitySearcher::class, $searcher);
            $this->assertSame($entity, $searcher->entity());
        }
    }
}
