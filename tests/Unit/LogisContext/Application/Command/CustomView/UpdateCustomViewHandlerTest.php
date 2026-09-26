<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Command\CustomView\UpdateCustomView;
use Logis\LogisContext\Application\Command\CustomView\UpdateCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewTitleAlreadyUsedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewTypeCannotChangeException;
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

final class UpdateCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private UpdateCustomViewHandler $handler;
    private User $owner;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();

        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new UpdateCustomViewHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );

        $this->owner = UserMother::create(id: 1, customer: CustomerMother::create());
        $this->users->add(user: $this->owner);
        $this->views->add(view: CustomViewMother::create(id: 1, user: $this->owner, title: 'Zebra scanners', isPublic: true));
    }

    public function test_the_owner_replaces_the_view(): void
    {
        $this->handler->handle(command: $this->command(title: 'Retired devices', viewType: 'my_assets'));

        $view = $this->views->findById(id: CustomViewId::fromInt(value: 1));
        $this->assertNotNull($view);
        $this->assertSame('Retired devices', $view->title()->value());
        $this->assertSame(['serial_number', 'status'], $view->columns()->toArray());
        $this->assertFalse($view->isPublic());
    }

    public function test_the_owner_may_keep_the_title(): void
    {
        $this->handler->handle(command: $this->command(title: 'Zebra scanners'));

        $this->assertSame(['serial_number', 'status'], $this->views->findById(id: CustomViewId::fromInt(value: 1))?->columns()->toArray());
    }

    public function test_a_title_of_another_view_of_the_owner_is_a_conflict(): void
    {
        $this->views->add(view: CustomViewMother::create(id: 2, user: $this->owner, title: 'Retired devices'));

        $this->expectException(CustomViewTitleAlreadyUsedException::class);

        $this->handler->handle(command: $this->command(title: 'Retired devices'));
    }

    public function test_the_type_cannot_change(): void
    {
        $this->expectException(CustomViewTypeCannotChangeException::class);

        $this->handler->handle(command: $this->command(viewType: 'my_tickets'));
    }

    public function test_a_colleague_cannot_change_a_public_view(): void
    {
        $this->users->add(user: UserMother::create(id: 2, email: 'colleague@logis.test', customer: $this->owner->customer()));

        $this->expectException(CustomViewAccessDeniedException::class);

        $this->handler->handle(command: $this->command(userId: 2));
    }

    public function test_a_missing_view_is_not_found(): void
    {
        $this->expectException(CustomViewNotFoundException::class);

        $this->handler->handle(command: $this->command(viewId: 99));
    }

    private function command(int $userId = 1, int $viewId = 1, string $title = 'Zebra scanners', ?string $viewType = null): UpdateCustomView
    {
        return new UpdateCustomView(
            userId: $userId,
            viewId: $viewId,
            title: $title,
            description: null,
            viewType: $viewType,
            columns: ['serial_number', 'status'],
            filters: [['column' => 'status', 'operator' => 'eq', 'value' => 'retired']],
            isPublic: false,
        );
    }
}
