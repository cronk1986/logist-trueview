<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\Query\CustomView\ListCustomViews;
use Logis\LogisContext\Application\Query\CustomView\ListCustomViewsHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewTypeException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class ListCustomViewsHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private ListCustomViewsHandler $handler;
    private User $user;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new ListCustomViewsHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );

        $customer = CustomerMother::create(id: 1);
        $this->user = UserMother::create(id: 1, customer: $customer);
        $colleague = UserMother::create(id: 2, email: 'colleague@logis.test', customer: $customer);
        $stranger = UserMother::create(id: 3, email: 'stranger@logis.test', customer: CustomerMother::create(id: 2, erpId: 'C-2'));
        $this->users->add(user: $this->user);

        $this->views->add(view: CustomViewMother::create(id: 1, user: $this->user, title: 'Mine', isDefault: true));
        $this->views->add(view: CustomViewMother::create(id: 2, user: $colleague, title: 'Colleague public', type: ViewType::MyContracts, columns: ['name'], filters: [], isPublic: true));
        $this->views->add(view: CustomViewMother::create(id: 3, user: $colleague, title: 'Colleague private'));
        $this->views->add(view: CustomViewMother::create(id: 4, user: $stranger, title: 'Stranger public', isPublic: true));
    }

    public function test_it_lists_own_views_and_public_views_of_the_same_customer_by_title(): void
    {
        $response = $this->handler->handle(query: new ListCustomViews(userId: 1, viewType: null));

        $this->assertSame([2, 1], array_column(array: $response->data(), column_key: 'id'));
        $this->assertSame(['total' => 2], $response->meta()?->toArray());
    }

    public function test_it_marks_ownership_and_the_own_default(): void
    {
        $items = $this->handler->handle(query: new ListCustomViews(userId: 1, viewType: null))->data();

        $this->assertSame(
            ['id' => 1, 'title' => 'Mine', 'description' => 'Every Zebra device', 'view_type' => 'my_assets', 'is_default' => true, 'is_public' => false, 'is_owner' => true],
            $items[1],
        );
        $this->assertFalse($items[0]['is_owner']);
    }

    public function test_it_filters_by_view_type(): void
    {
        $response = $this->handler->handle(query: new ListCustomViews(userId: 1, viewType: 'my_contracts'));

        $this->assertSame([2], array_column(array: $response->data(), column_key: 'id'));
    }

    public function test_a_user_without_a_customer_gets_no_views(): void
    {
        $this->users->add(user: UserMother::create(id: 9, email: 'nobody@logis.test', customer: null));

        $response = $this->handler->handle(query: new ListCustomViews(userId: 9, viewType: null));

        $this->assertSame([], $response->data());
        $this->assertSame(['total' => 0], $response->meta()?->toArray());
    }

    public function test_an_unknown_view_type_is_rejected(): void
    {
        $this->expectException(InvalidViewTypeException::class);

        $this->handler->handle(query: new ListCustomViews(userId: 1, viewType: 'my_invoices'));
    }
}
