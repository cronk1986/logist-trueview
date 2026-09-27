<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Command\CustomView\CreateCustomView;
use Logis\LogisContext\Application\Command\CustomView\CreateCustomViewHandler;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Event\CustomView\CustomViewCreated;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewTitleAlreadyUsedException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewTypeException;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\InMemoryCustomViewRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CreateCustomViewHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryCustomViewRepository $views;
    private CreateCustomViewHandler $handler;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();

        $this->users = new InMemoryUserRepository();
        $this->views = new InMemoryCustomViewRepository();
        $this->handler = new CreateCustomViewHandler(
            access: new CustomViewAccess(users: $this->users, views: $this->views, policy: new ViewPolicy()),
            views: $this->views,
        );
        $this->users->add(user: UserMother::create(id: 1, customer: CustomerMother::create(id: 3)));
    }

    public function test_it_creates_a_view_for_the_customer_of_its_owner(): void
    {
        $this->handler->handle(command: $this->command());

        $view = $this->views->findByOwnerAndTitle(owner: UserId::fromInt(value: 1), title: CustomViewTitle::fromString(value: 'Zebra scanners'));

        $this->assertNotNull($view);
        $this->assertSame(3, $view->customer()->id()->value());
        $this->assertSame(ViewType::MyAssets, $view->type());
        $this->assertSame(['serial_number', 'location'], $view->columns()->toArray());
        $this->assertSame([['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']], $view->filters()->toArray());
        $this->assertSame('Every Zebra', $view->description());
        $this->assertTrue($view->isPublic());
        $this->assertFalse($view->isDefault());
        $this->assertNotNull(DomainEventPublisher::lastEventOfType(domainEventType: CustomViewCreated::class));
    }

    public function test_the_owner_cannot_reuse_a_title(): void
    {
        $this->views->add(view: CustomViewMother::create(id: 1, user: $this->users->findById(id: UserId::fromInt(value: 1)), title: 'Zebra scanners'));

        $this->expectException(CustomViewTitleAlreadyUsedException::class);
        $this->expectExceptionMessage('You already have a view called "Zebra scanners".');

        $this->handler->handle(command: $this->command());
    }

    public function test_a_user_without_a_customer_cannot_create_views(): void
    {
        $this->users->add(user: UserMother::create(id: 2, email: 'nobody@logis.test', customer: null));

        $this->expectException(CustomViewAccessDeniedException::class);

        $this->handler->handle(command: $this->command(userId: 2));
    }

    public function test_the_definition_must_fit_the_catalog(): void
    {
        $this->expectException(InvalidViewColumnsException::class);

        $this->handler->handle(command: $this->command(columns: ['customer_id']));
    }

    public function test_the_view_type_must_exist(): void
    {
        $this->expectException(InvalidViewTypeException::class);

        $this->handler->handle(command: $this->command(viewType: 'my_invoices'));
    }

    /**
     * @param list<string> $columns
     */
    private function command(int $userId = 1, string $viewType = 'my_assets', array $columns = ['serial_number', 'location']): CreateCustomView
    {
        return new CreateCustomView(
            userId: $userId,
            title: ' Zebra scanners ',
            description: 'Every Zebra',
            viewType: $viewType,
            columns: $columns,
            filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']],
            isPublic: true,
        );
    }
}
