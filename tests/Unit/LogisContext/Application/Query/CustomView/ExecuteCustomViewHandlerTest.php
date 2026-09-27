<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Query\CustomView\ExecuteCustomView;
use Logis\LogisContext\Application\Query\CustomView\ExecuteCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilders;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewPageSizeException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewSortException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\SortDirection;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\InMemoryViewBuilder;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class ExecuteCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private InMemoryViewBuilder $assets;
    private ExecuteCustomViewHandler $handler;
    private User $owner;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $page = ViewPage::from(rows: [['id' => 77, 'serial_number' => 'ZB-1', 'model' => 'ZT410']], nextCursor: 'next');
        $this->assets = new InMemoryViewBuilder(type: ViewType::MyAssets, page: $page);
        $builders = [$this->assets];

        foreach ([ViewType::MyTickets, ViewType::MyContracts, ViewType::MyLocations] as $type) {
            $builders[] = new InMemoryViewBuilder(type: $type, page: ViewPage::empty());
        }

        $this->handler = new ExecuteCustomViewHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            builders: new ViewBuilders(builders: $builders),
        );

        $this->owner = UserMother::create(id: 1, customer: CustomerMother::create(id: 1));
        $this->users->add(user: $this->owner);
        $this->views->add(view: CustomViewMother::create(id: 5, user: $this->owner, title: 'Zebra scanners', isPublic: true));
    }

    public function test_it_returns_the_page_of_the_view(): void
    {
        $response = $this->handler->handle(query: $this->query());

        $this->assertSame(
            [
                'view' => ['id' => 5, 'title' => 'Zebra scanners', 'view_type' => 'my_assets'],
                'columns' => ['serial_number', 'model'],
                'rows' => [['id' => 77, 'serial_number' => 'ZB-1', 'model' => 'ZT410']],
            ],
            $response->data(),
        );
        $this->assertSame(['limit' => 50, 'next_cursor' => 'next', 'has_more' => true], $response->meta()?->toArray());
    }

    public function test_it_passes_the_definition_and_the_defaults_to_the_builder(): void
    {
        $this->handler->handle(query: $this->query());

        $criteria = $this->assets->executions()[0];
        $this->assertSame(['serial_number', 'model'], $criteria->definition->columns()->toArray());
        $this->assertSame('id', $criteria->sort->field());
        $this->assertSame(SortDirection::Desc, $criteria->sort->direction());
        $this->assertSame(50, $criteria->pageSize->value());
        $this->assertNull($criteria->cursor);
        $this->assertNull($criteria->term);
    }

    public function test_it_passes_the_sort_cursor_and_term(): void
    {
        $this->handler->handle(query: $this->query(limit: 1000, cursor: 'abc', sortField: 'created_at', sortDirection: 'asc', term: ' zt4 '));

        $criteria = $this->assets->executions()[0];
        $this->assertSame('created_at', $criteria->sort->field());
        $this->assertSame(SortDirection::Asc, $criteria->sort->direction());
        $this->assertSame(1000, $criteria->pageSize->value());
        $this->assertSame('abc', $criteria->cursor);
        $this->assertSame('zt4', $criteria->term?->value());
    }

    public function test_the_builder_receives_the_definition_refined_by_the_user_filters(): void
    {
        $this->views->add(view: CustomViewMother::create(
            id: 7,
            user: $this->owner,
            title: 'Restricted status',
            columns: ['serial_number', 'location'],
            filters: [['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'restricted']],
        ));

        $this->handler->handle(query: $this->query(viewId: 7, filters: [
            ['column' => 'status', 'operator' => 'eq', 'value' => 'inactive'],
            ['column' => 'location', 'operator' => 'in', 'value' => [3]],
        ]));

        $this->assertSame(
            [
                ['column' => 'location', 'operator' => 'in', 'value' => [3], 'user_filter' => 'open'],
                ['column' => 'status', 'operator' => 'eq', 'value' => 'inactive', 'user_filter' => 'restricted'],
            ],
            $this->assets->executions()[0]->definition->filters()->toArray(),
        );
    }

    public function test_without_user_filters_the_builder_receives_the_saved_filters(): void
    {
        $this->handler->handle(query: $this->query());

        $this->assertSame(
            [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked']],
            $this->assets->executions()[0]->definition->filters()->toArray(),
        );
    }

    public function test_a_user_filter_on_a_locked_field_is_rejected(): void
    {
        $this->expectException(InvalidViewFilterException::class);

        $this->handler->handle(query: $this->query(filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Honeywell']]));
    }

    public function test_a_sort_without_direction_is_descending(): void
    {
        $this->handler->handle(query: $this->query(sortField: 'serial_number'));

        $this->assertSame(SortDirection::Desc, $this->assets->executions()[0]->sort->direction());
    }

    public function test_a_blank_term_means_no_text_search(): void
    {
        $this->handler->handle(query: $this->query(term: '   '));

        $this->assertNull($this->assets->executions()[0]->term);
    }

    public function test_the_rows_belong_to_the_customers_of_the_user_who_executes_the_view(): void
    {
        $colleague = UserMother::create(id: 2, email: 'colleague@logis.test', customer: CustomerMother::create(id: 1), canViewSubAccounts: true);
        $this->users->add(user: $colleague);

        $this->handler->handle(query: $this->query(userId: 2));

        $customers = $this->assets->executions()[0]->customers;
        $this->assertSame(1, $customers->customer()?->value());
        $this->assertTrue($customers->includesSubAccounts());
    }

    public function test_an_owner_who_lost_their_customer_gets_no_rows_without_running_the_builder(): void
    {
        $this->views->add(view: CustomViewMother::create(id: 6, user: UserMother::create(id: 3, email: 'former@logis.test', customer: CustomerMother::create(id: 9, erpId: 'C-9'))));
        $this->users->add(user: UserMother::create(id: 3, email: 'former@logis.test', customer: null));

        $response = $this->handler->handle(query: $this->query(userId: 3, viewId: 6));

        $this->assertSame([], $response->data()['rows']);
        $this->assertSame([], $this->assets->executions());
    }

    public function test_a_view_the_user_cannot_see_is_not_found(): void
    {
        $this->users->add(user: UserMother::create(id: 4, email: 'stranger@logis.test', customer: CustomerMother::create(id: 2, erpId: 'C-2')));

        $this->expectException(CustomViewNotFoundException::class);

        $this->handler->handle(query: $this->query(userId: 4));
    }

    public function test_a_field_that_is_not_sortable_is_rejected(): void
    {
        $this->expectException(InvalidViewSortException::class);

        $this->handler->handle(query: $this->query(sortField: 'location'));
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->expectException(InvalidViewPageSizeException::class);

        $this->handler->handle(query: $this->query(limit: 1001));
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    private function query(
        int $userId = 1,
        int $viewId = 5,
        int $limit = 50,
        ?string $cursor = null,
        ?string $sortField = null,
        ?string $sortDirection = null,
        ?string $term = null,
        array $filters = [],
    ): ExecuteCustomView {
        return new ExecuteCustomView(
            userId: $userId,
            viewId: $viewId,
            limit: $limit,
            cursor: $cursor,
            sortField: $sortField,
            sortDirection: $sortDirection,
            term: $term,
            filters: $filters,
        );
    }
}
