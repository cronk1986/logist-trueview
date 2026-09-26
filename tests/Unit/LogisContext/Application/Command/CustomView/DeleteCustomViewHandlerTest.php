<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Command\CustomView\DeleteCustomView;
use Logis\LogisContext\Application\Command\CustomView\DeleteCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Event\CustomView\CustomViewDeleted;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class DeleteCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private DeleteCustomViewHandler $handler;
    private User $owner;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();

        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new DeleteCustomViewHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );

        $this->owner = UserMother::create(id: 1, customer: CustomerMother::create());
        $this->users->add(user: $this->owner);
        $this->views->add(view: CustomViewMother::create(id: 3, user: $this->owner, isPublic: true));
    }

    public function test_the_owner_deletes_the_view(): void
    {
        $this->handler->handle(command: new DeleteCustomView(userId: 1, viewId: 3));

        $this->assertSame([1], $this->views->lockedOwners());

        $this->assertNull($this->views->findById(id: CustomViewId::fromInt(value: 3)));

        $event = DomainEventPublisher::lastEventOfType(domainEventType: CustomViewDeleted::class);
        $this->assertInstanceOf(CustomViewDeleted::class, $event);
        $this->assertSame(3, $event->viewId()->value());
    }

    public function test_a_colleague_cannot_delete_a_public_view(): void
    {
        $this->users->add(user: UserMother::create(id: 2, email: 'colleague@logis.test', customer: $this->owner->customer()));

        $this->expectException(CustomViewAccessDeniedException::class);

        $this->handler->handle(command: new DeleteCustomView(userId: 2, viewId: 3));
    }

    public function test_a_missing_view_is_not_found(): void
    {
        $this->expectException(CustomViewNotFoundException::class);

        $this->handler->handle(command: new DeleteCustomView(userId: 1, viewId: 99));
    }
}
