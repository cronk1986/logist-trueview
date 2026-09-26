<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\Query\CustomView\GetCustomView;
use Logis\LogisContext\Application\Query\CustomView\GetCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class GetCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private GetCustomViewHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new GetCustomViewHandler(access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()));
    }

    public function test_it_returns_the_details_of_a_view_the_user_can_see(): void
    {
        $customer = CustomerMother::create();
        $owner = UserMother::create(id: 1, name: 'Owner', customer: $customer);
        $colleague = UserMother::create(id: 2, email: 'colleague@logis.test', customer: $customer);
        $this->users->add(user: $colleague);
        $this->views->add(view: CustomViewMother::create(id: 5, user: $owner, isDefault: true, isPublic: true));

        $data = $this->handler->handle(query: new GetCustomView(userId: 2, viewId: 5))->data();

        $this->assertSame(5, $data['id']);
        $this->assertFalse($data['is_owner']);
        $this->assertFalse($data['is_default']);
        $this->assertSame(['id' => 1, 'name' => 'Owner'], $data['owner']);
    }

    public function test_a_view_the_user_cannot_see_is_not_found(): void
    {
        $this->users->add(user: UserMother::create(id: 2, customer: CustomerMother::create(id: 2, erpId: 'C-2')));
        $this->views->add(view: CustomViewMother::create(id: 5, user: UserMother::create(id: 1, customer: CustomerMother::create(id: 1)), isPublic: true));

        $this->expectException(CustomViewNotFoundException::class);

        $this->handler->handle(query: new GetCustomView(userId: 2, viewId: 5));
    }
}
