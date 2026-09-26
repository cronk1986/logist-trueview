<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\Cache;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewPageSizeException;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Cache\CachedViewBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\InMemoryViewBuilder;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class CachedViewBuilderTest extends TestCase
{
    private InMemoryViewBuilder $assets;
    private CachedViewBuilder $cachedAssets;

    protected function setUp(): void
    {
        $this->assets = new InMemoryViewBuilder(
            type: ViewType::MyAssets,
            page: ViewPage::from(rows: [['id' => 1, 'location' => ['id' => 3, 'label' => 'Main']]], nextCursor: 'next'),
        );
        $this->cachedAssets = new CachedViewBuilder(builder: $this->assets, cache: new Repository(store: new ArrayStore()), ttlSeconds: 300);
    }

    public function test_it_delegates_the_type(): void
    {
        $this->assertSame(ViewType::MyAssets, $this->cachedAssets->type());
    }

    public function test_a_repeated_execution_is_served_from_the_cache(): void
    {
        $first = $this->cachedAssets->execute(criteria: $this->criteria());
        $second = $this->cachedAssets->execute(criteria: $this->criteria());

        $this->assertCount(1, $this->assets->executions());
        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame('next', $second->nextCursor());
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('differentExecutions')]
    public function test_a_different_execution_misses_the_cache(array $changes): void
    {
        $this->cachedAssets->execute(criteria: $this->criteria());
        $this->cachedAssets->execute(criteria: $this->criteria(...$changes));

        $this->assertCount(2, $this->assets->executions());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function differentExecutions(): array
    {
        return [
            'another customer' => [['customer' => 2]],
            'with sub-accounts' => [['subAccounts' => true]],
            'other columns' => [['columns' => ['serial_number', 'model']]],
            'other filters' => [['filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Honeywell']]]],
            'other sort field' => [['sortField' => 'created_at']],
            'other sort direction' => [['sortField' => 'id', 'sortDirection' => 'asc']],
            'a search term' => [['term' => 'zebra']],
            'other page size' => [['pageSize' => 10]],
            'a cursor' => [['cursor' => 'abc']],
        ];
    }

    public function test_a_term_that_only_differs_in_case_hits_the_same_entry(): void
    {
        $this->cachedAssets->execute(criteria: $this->criteria(term: 'Zebra'));
        $this->cachedAssets->execute(criteria: $this->criteria(term: 'ZEBRA'));

        $this->assertCount(1, $this->assets->executions());
    }

    public function test_the_entries_of_each_view_type_are_separate(): void
    {
        $tickets = new InMemoryViewBuilder(type: ViewType::MyTickets, page: ViewPage::empty());
        $cache = new Repository(store: new ArrayStore());
        $cachedAssets = new CachedViewBuilder(builder: $this->assets, cache: $cache, ttlSeconds: 300);
        $cachedTickets = new CachedViewBuilder(builder: $tickets, cache: $cache, ttlSeconds: 300);

        $cachedAssets->execute(criteria: $this->criteria(columns: ['id']));
        $cachedTickets->execute(criteria: ViewExecutionCriteriaMother::create(type: ViewType::MyTickets, customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: 1))));

        $this->assertCount(1, $tickets->executions());
    }

    public function test_a_failed_execution_is_not_cached(): void
    {
        $failing = new class () implements ViewBuilder {
            public int $calls = 0;

            public function type(): ViewType
            {
                return ViewType::MyAssets;
            }

            public function execute(ViewExecutionCriteria $criteria): ViewPage
            {
                $this->calls++;

                throw InvalidViewPageSizeException::outOfRange(value: 0, min: 1, max: 1000);
            }
        };
        $cached = new CachedViewBuilder(builder: $failing, cache: new Repository(store: new ArrayStore()), ttlSeconds: 300);

        foreach ([1, 2] as $attempt) {
            try {
                $cached->execute(criteria: $this->criteria());
            } catch (InvalidViewPageSizeException) {
            }
        }

        $this->assertSame(2, $failing->calls);
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function criteria(
        int $customer = 1,
        bool $subAccounts = false,
        array $columns = ['serial_number', 'location'],
        array $filters = [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']],
        ?string $sortField = null,
        string $sortDirection = 'desc',
        int $pageSize = 50,
        ?string $cursor = null,
        ?string $term = null,
    ): ViewExecutionCriteria {
        $customerId = CustomerId::fromInt(value: $customer);

        return ViewExecutionCriteriaMother::create(
            type: ViewType::MyAssets,
            customers: $subAccounts ? VisibleCustomers::customerWithSubAccounts(customer: $customerId) : VisibleCustomers::ownCustomer(customer: $customerId),
            columns: $columns,
            filters: $filters,
            sortField: $sortField,
            sortDirection: $sortDirection,
            pageSize: $pageSize,
            cursor: $cursor,
            term: $term,
        );
    }
}
