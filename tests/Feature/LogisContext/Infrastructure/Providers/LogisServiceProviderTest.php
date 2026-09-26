<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Providers;

use Logis\LogisContext\Application\Services\CustomView\ViewBuilders;
use Logis\LogisContext\Application\Services\Search\EntitySearchers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Cache\CachedEntitySearcher;
use Logis\LogisContext\Infrastructure\Cache\CachedViewBuilder;
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

    public function test_every_view_type_has_a_cached_builder_in_the_container(): void
    {
        $builders = $this->app->make(abstract: ViewBuilders::class);

        foreach (ViewType::cases() as $type) {
            $builder = $builders->for(type: $type);

            $this->assertInstanceOf(CachedViewBuilder::class, $builder);
            $this->assertSame($type, $builder->type());
        }
    }
}
