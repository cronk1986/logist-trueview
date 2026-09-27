<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Command\CustomView\SetDefaultCustomView;
use Logis\LogisContext\Application\Command\CustomView\SetDefaultCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Event\CustomView\CustomViewSetAsDefault;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class SetDefaultCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private SetDefaultCustomViewHandler $handler;
    private User $owner;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();

        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new SetDefaultCustomViewHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );

        $this->owner = UserMother::create(id: 1, customer: CustomerMother::create());
        $this->users->add(user: $this->owner);
        $this->views->add(view: CustomViewMother::create(id: 1, user: $this->owner, title: 'Current default', isDefault: true));
        $this->views->add(view: CustomViewMother::create(id: 2, user: $this->owner, title: 'New default', isPublic: true));
        $this->views->add(view: CustomViewMother::create(id: 3, user: $this->owner, title: 'Tickets', type: ViewType::MyTickets, columns: ['subject'], filters: [], isDefault: true));
    }

    public function test_it_replaces_the_default_of_the_same_type(): void
    {
        $this->handler->handle(command: new SetDefaultCustomView(userId: 1, viewId: 2));

        $this->assertFalse($this->isDefault(id: 1));
        $this->assertTrue($this->isDefault(id: 2));
        $this->assertTrue($this->isDefault(id: 3));

        $event = DomainEventPublisher::lastEventOfType(domainEventType: CustomViewSetAsDefault::class);
        $this->assertInstanceOf(CustomViewSetAsDefault::class, $event);
        $this->assertSame(2, $event->viewId()->value());
        $this->assertSame(1, $event->ownerId()->value());
        $this->assertSame(ViewType::MyAssets, $event->type());
    }

    public function test_setting_the_current_default_again_changes_nothing(): void
    {
        $this->handler->handle(command: new SetDefaultCustomView(userId: 1, viewId: 1));

        $this->assertTrue($this->isDefault(id: 1));
        $this->assertNull(DomainEventPublisher::lastEventOfType(domainEventType: CustomViewSetAsDefault::class));
    }

    public function test_a_colleague_cannot_set_a_public_view_as_default(): void
    {
        $this->users->add(user: UserMother::create(id: 2, email: 'colleague@logis.test', customer: $this->owner->customer()));

        $this->expectException(CustomViewAccessDeniedException::class);

        $this->handler->handle(command: new SetDefaultCustomView(userId: 2, viewId: 2));
    }

    public function test_a_missing_view_is_not_found(): void
    {
        $this->expectException(CustomViewNotFoundException::class);

        $this->handler->handle(command: new SetDefaultCustomView(userId: 1, viewId: 99));
    }

    private function isDefault(int $id): bool
    {
        return (bool) $this->views->findById(id: CustomViewId::fromInt(value: $id))?->isDefault();
    }
}
