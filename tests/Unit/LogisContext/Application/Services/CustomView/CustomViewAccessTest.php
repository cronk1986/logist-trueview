<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewAccessTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private CustomViewAccess $access;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->access = new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy());
    }

    public function test_it_loads_the_user(): void
    {
        $this->users->add(user: UserMother::create(id: 4));

        $this->assertSame(4, $this->access->user(userId: 4)->id()->value());
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->access->user(userId: 4);
    }

    public function test_the_owner_can_manage_the_view(): void
    {
        $owner = UserMother::create(id: 1, customer: CustomerMother::create());
        $this->views->add(view: CustomViewMother::create(id: 7, user: $owner));

        $this->assertSame(7, $this->access->viewManagedBy(user: $owner, viewId: 7)->savedId()->value());
    }

    public function test_a_missing_view_is_not_found(): void
    {
        $this->expectException(CustomViewNotFoundException::class);
        $this->expectExceptionMessage('View 7 not found.');

        $this->access->viewVisibleTo(user: UserMother::create(), viewId: 7);
    }

    public function test_a_view_the_user_cannot_see_is_not_found(): void
    {
        $customer = CustomerMother::create();
        $this->views->add(view: CustomViewMother::create(id: 7, user: UserMother::create(id: 1, customer: $customer)));

        $this->expectException(CustomViewNotFoundException::class);

        $this->access->viewManagedBy(user: UserMother::create(id: 2, email: 'other@logis.test', customer: $customer), viewId: 7);
    }

    public function test_a_visible_view_of_another_owner_cannot_be_managed(): void
    {
        $customer = CustomerMother::create();
        $this->views->add(view: CustomViewMother::create(id: 7, user: UserMother::create(id: 1, customer: $customer), isPublic: true));
        $colleague = UserMother::create(id: 2, email: 'other@logis.test', customer: $customer);

        $this->assertSame(7, $this->access->viewVisibleTo(user: $colleague, viewId: 7)->savedId()->value());

        $this->expectException(CustomViewAccessDeniedException::class);
        $this->expectExceptionMessage('Only the owner of view 7 can change it.');

        $this->access->viewManagedBy(user: $colleague, viewId: 7);
    }
}
