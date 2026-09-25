<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\Cache;

use Carbon\CarbonImmutable;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Cache\CachedEntitySearcher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\InMemoryEntitySearcher;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\SearchCriteriaMother;

final class CachedEntitySearcherTest extends TestCase
{
    private const TTL_SECONDS = 300;

    private Repository $cache;
    private InMemoryEntitySearcher $tickets;
    private CachedEntitySearcher $cachedTickets;

    protected function setUp(): void
    {
        $this->cache = new Repository(store: new ArrayStore());
        $this->tickets = new InMemoryEntitySearcher(
            entity: SearchableEntity::Tickets,
            result: EntitySearchResult::from(results: [['id' => 1, 'subject' => 'Zebra printer']], total: 8),
        );
        $this->cachedTickets = $this->cached(searcher: $this->tickets);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_it_delegates_the_entity(): void
    {
        $this->assertSame(SearchableEntity::Tickets, $this->cachedTickets->entity());
    }

    public function test_a_repeated_search_is_served_from_the_cache(): void
    {
        $first = $this->cachedTickets->search(criteria: $this->criteria());
        $second = $this->cachedTickets->search(criteria: $this->criteria());

        $this->assertCount(1, $this->tickets->calls());
        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame([['id' => 1, 'subject' => 'Zebra printer']], $second->results());
        $this->assertSame(8, $second->total());
    }

    public function test_a_term_that_only_differs_in_case_hits_the_same_entry(): void
    {
        $this->cachedTickets->search(criteria: $this->criteria(term: 'Zebra'));
        $this->cachedTickets->search(criteria: $this->criteria(term: 'ZEBRA'));

        $this->assertCount(1, $this->tickets->calls());
    }

    public function test_including_the_sub_accounts_is_a_different_entry(): void
    {
        $this->cachedTickets->search(criteria: $this->criteria());
        $this->cachedTickets->search(criteria: $this->criteria(withSubAccounts: false));

        $this->assertCount(2, $this->tickets->calls());
    }

    public function test_other_customers_a_different_term_or_limit_miss_the_cache(): void
    {
        $this->cachedTickets->search(criteria: $this->criteria());
        $this->cachedTickets->search(criteria: $this->criteria(customerId: 3));
        $this->cachedTickets->search(criteria: $this->criteria(term: 'honeywell'));
        $this->cachedTickets->search(criteria: $this->criteria(limit: 10));

        $this->assertCount(4, $this->tickets->calls());
    }

    public function test_each_entity_has_its_own_entry(): void
    {
        $assets = InMemoryEntitySearcher::returningNothing(entity: SearchableEntity::Assets);

        $this->cachedTickets->search(criteria: $this->criteria());
        $result = $this->cached(searcher: $assets)->search(criteria: $this->criteria());

        $this->assertCount(1, $assets->calls());
        $this->assertSame(0, $result->total());
    }

    public function test_the_entry_expires_after_the_ttl(): void
    {
        $now = CarbonImmutable::now()->utc();
        CarbonImmutable::setTestNow(testNow: $now);
        $this->cachedTickets->search(criteria: $this->criteria());

        CarbonImmutable::setTestNow(testNow: $now->addSeconds(self::TTL_SECONDS - 1));
        $this->cachedTickets->search(criteria: $this->criteria());
        CarbonImmutable::setTestNow(testNow: $now->addSeconds(self::TTL_SECONDS + 1));
        $this->cachedTickets->search(criteria: $this->criteria());

        $this->assertCount(2, $this->tickets->calls());
    }

    private function cached(InMemoryEntitySearcher $searcher): CachedEntitySearcher
    {
        return new CachedEntitySearcher(searcher: $searcher, cache: $this->cache, ttlSeconds: self::TTL_SECONDS);
    }

    private function criteria(string $term = 'zebra', int $limit = 5, int $customerId = 1, bool $withSubAccounts = true): SearchCriteria
    {
        return SearchCriteriaMother::create(term: $term, limit: $limit, customerId: $customerId, withSubAccounts: $withSubAccounts);
    }
}
