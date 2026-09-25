<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\GlobalSearch;

use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchers;
use Logis\LogisContext\Application\Query\GlobalSearch\EntitySearchResult;
use Logis\LogisContext\Application\Query\GlobalSearch\GlobalSearch;
use Logis\LogisContext\Application\Query\GlobalSearch\GlobalSearchHandler;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use Logis\LogisContext\Domain\Model\Customer\VisibleCustomersResolver;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\Customer\InMemoryCustomerRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class GlobalSearchHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomerRepository $customers;
    private InMemoryEntitySearcher $tickets;
    private InMemoryEntitySearcher $assets;
    private GlobalSearchHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->customers = new InMemoryCustomerRepository();
        $this->tickets = new InMemoryEntitySearcher(
            entity: SearchableEntity::Tickets,
            result: EntitySearchResult::from(results: [['id' => 1], ['id' => 2]], total: 7),
        );
        $this->assets = new InMemoryEntitySearcher(
            entity: SearchableEntity::Assets,
            result: EntitySearchResult::from(results: [['id' => 3]], total: 1),
        );
        $this->handler = new GlobalSearchHandler(
            users: $this->users,
            visibleCustomers: new VisibleCustomersResolver(customers: $this->customers),
            searchers: new EntitySearchers(searchers: [$this->assets, $this->tickets]),
        );

        $parent = CustomerMother::create(id: 1);
        $this->customers->add(customer: $parent);
        $this->customers->add(customer: CustomerMother::create(id: 2, erpId: 'C-1001', parent: $parent));
    }

    public function test_it_returns_the_results_of_each_requested_entity(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, limit: 2));

        $this->assertSame([
            'tickets' => ['results' => [['id' => 1], ['id' => 2]], 'total' => 7, 'has_more' => true],
        ], $response->data());
    }

    public function test_it_returns_the_entities_in_canonical_order_whatever_the_requested_order(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, entities: ['assets', 'tickets']));

        $this->assertSame(['tickets', 'assets'], array_keys(array: $response->data()));
    }

    public function test_it_only_asks_the_searchers_of_the_requested_entities(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, entities: ['assets']));

        $this->assertSame(['assets'], array_keys(array: $response->data()));
        $this->assertSame([], $this->tickets->calls());
        $this->assertCount(1, $this->assets->calls());
    }

    public function test_has_more_is_false_when_the_limit_covers_the_total(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, limit: 7));

        $this->assertFalse($response->data()['tickets']['has_more']);
    }

    public function test_it_reports_the_trimmed_term_and_the_limit_in_the_meta(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, term: '  zebra ', limit: 3));

        $this->assertSame(['query' => 'zebra', 'limit_per_entity' => 3], $response->meta()?->toArray());
    }

    public function test_it_passes_the_criteria_to_the_searcher(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1), canViewSubAccounts: true));

        $this->handler->handle(query: $this->search(userId: 5, term: 'zebra', limit: 4));

        $criteria = $this->tickets->calls()[0];
        $this->assertSame('zebra', $criteria->term->value());
        $this->assertSame(4, $criteria->limit->value());
        $this->assertSame([1, 2], $criteria->customers->values());
    }

    public function test_it_skips_the_entities_that_were_not_requested(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: CustomerMother::create(id: 1)));

        $response = $this->handler->handle(query: $this->search(userId: 5, entities: []));

        $this->assertSame([], $response->data());
        $this->assertSame([], $this->tickets->calls());
    }

    public function test_a_user_without_a_customer_gets_empty_results_without_searching(): void
    {
        $this->users->add(user: UserMother::create(id: 5, customer: null));

        $response = $this->handler->handle(query: $this->search(userId: 5));

        $this->assertSame(['tickets' => ['results' => [], 'total' => 0, 'has_more' => false]], $response->data());
        $this->assertSame([], $this->tickets->calls());
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->handler->handle(query: $this->search(userId: 99));
    }

    /**
     * @param list<string> $entities
     */
    private function search(int $userId, string $term = 'zebra', int $limit = 5, array $entities = ['tickets']): GlobalSearch
    {
        return new GlobalSearch(userId: $userId, term: $term, limit: $limit, entities: $entities);
    }
}
