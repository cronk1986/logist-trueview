<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\Query\CustomView\GetCustomViewByTitle;
use Logis\LogisContext\Application\Query\CustomView\GetCustomViewByTitleHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class GetCustomViewByTitleHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private GetCustomViewByTitleHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new GetCustomViewByTitleHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );
    }

    public function test_it_returns_the_owners_view_with_that_title(): void
    {
        $owner = UserMother::create(id: 1, customer: CustomerMother::create());
        $this->users->add(user: $owner);
        $this->views->add(view: CustomViewMother::create(id: 4, user: $owner, title: 'Zebra scanners'));

        $data = $this->handler->handle(query: new GetCustomViewByTitle(userId: 1, title: ' Zebra scanners '))->data();

        $this->assertSame(4, $data['id']);
        $this->assertTrue($data['is_owner']);
    }

    public function test_a_title_of_another_owner_is_not_found(): void
    {
        $customer = CustomerMother::create();
        $this->users->add(user: UserMother::create(id: 1, customer: $customer));
        $this->views->add(view: CustomViewMother::create(id: 4, user: UserMother::create(id: 2, customer: $customer), title: 'Zebra scanners', isPublic: true));

        $this->expectException(CustomViewNotFoundException::class);
        $this->expectExceptionMessage('View "Zebra scanners" not found.');

        $this->handler->handle(query: new GetCustomViewByTitle(userId: 1, title: 'Zebra scanners'));
    }
}
