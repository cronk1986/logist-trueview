<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Event\CustomView\CustomViewCreated;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewLinkedToAnotherCustomerException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotSavedException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewTest extends TestCase
{
    protected function setUp(): void
    {
        DomainEventPublisher::reset();
    }

    public function test_a_new_view_belongs_to_the_customer_of_its_owner_and_is_not_default(): void
    {
        $owner = UserMother::create(id: 5, customer: CustomerMother::create(id: 3));

        $view = CustomView::create(
            owner: $owner,
            title: CustomViewTitle::fromString(value: 'Zebra scanners'),
            description: null,
            type: ViewType::MyAssets,
            columns: ViewColumns::fromList(columns: ['serial_number']),
            filters: ViewFilters::fromList(filters: []),
            isPublic: true,
        );

        $this->assertNull($view->id());
        $this->assertSame(3, $view->customer()->id()->value());
        $this->assertFalse($view->isDefault());
        $this->assertTrue($view->isPublic());

        $event = DomainEventPublisher::lastEventOfType(domainEventType: CustomViewCreated::class);
        $this->assertInstanceOf(CustomViewCreated::class, $event);
        $this->assertSame(5, $event->ownerId()->value());
        $this->assertSame('Zebra scanners', $event->title()->value());
    }

    public function test_an_owner_without_a_customer_cannot_create_a_view(): void
    {
        $this->expectException(CustomViewAccessDeniedException::class);

        CustomView::create(
            owner: UserMother::create(customer: null),
            title: CustomViewTitle::fromString(value: 'Zebra scanners'),
            description: null,
            type: ViewType::MyAssets,
            columns: ViewColumns::fromList(columns: ['serial_number']),
            filters: ViewFilters::fromList(filters: []),
            isPublic: false,
        );
    }

    public function test_it_holds_its_owner_customer_and_configuration(): void
    {
        $owner = UserMother::create(id: 5, customer: CustomerMother::create(id: 1));

        $view = CustomViewMother::create(id: 2, user: $owner, isDefault: true, isPublic: true);

        $this->assertSame(2, $view->id()?->value());
        $this->assertSame($owner, $view->user());
        $this->assertSame(1, $view->customer()->id()->value());
        $this->assertSame('Zebra scanners', $view->title()->value());
        $this->assertSame('Every Zebra device', $view->description());
        $this->assertSame(ViewType::MyAssets, $view->type());
        $this->assertSame(['serial_number', 'model'], $view->columns()->toArray());
        $this->assertSame([['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']], $view->filters()->toArray());
        $this->assertTrue($view->isDefault());
        $this->assertTrue($view->isPublic());
    }

    public function test_a_view_must_belong_to_the_customer_of_its_owner(): void
    {
        $this->expectException(CustomViewLinkedToAnotherCustomerException::class);

        CustomViewMother::create(user: UserMother::create(customer: CustomerMother::create(id: 1)), customer: CustomerMother::create(id: 2));
    }

    public function test_an_owner_without_a_customer_cannot_own_a_view(): void
    {
        $this->expectException(CustomViewLinkedToAnotherCustomerException::class);

        CustomViewMother::create(user: UserMother::create(customer: null));
    }

    public function test_it_knows_its_owner(): void
    {
        $owner = UserMother::create(id: 5, customer: CustomerMother::create());
        $view = CustomViewMother::create(user: $owner);

        $this->assertTrue($view->isOwnedBy(user: $owner));
        $this->assertFalse($view->isOwnedBy(user: UserMother::create(id: 6, customer: $owner->customer())));
    }

    public function test_a_view_has_no_id_until_it_is_saved(): void
    {
        $view = CustomViewMother::create(id: null);

        $this->assertNull($view->id());

        $this->expectException(CustomViewNotSavedException::class);

        $view->savedId();
    }

    public function test_its_columns_must_belong_to_the_catalog_of_its_type(): void
    {
        $this->expectException(InvalidViewColumnsException::class);

        $this->viewOfType(type: ViewType::MyContracts, columns: ['serial_number'], filters: []);
    }

    public function test_its_filters_must_fit_the_catalog_of_its_type(): void
    {
        $this->expectException(InvalidViewFilterException::class);

        $this->viewOfType(type: ViewType::MyTickets, columns: ['subject'], filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']]);
    }

    public function test_it_exposes_its_definition(): void
    {
        $view = $this->viewOfType(type: ViewType::MyLocations, columns: ['title', 'city'], filters: []);

        $this->assertSame(ViewType::MyLocations, $view->definition()->type());
        $this->assertSame(['title', 'city'], $view->columns()->toArray());
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function viewOfType(ViewType $type, array $columns, array $filters): CustomView
    {
        $owner = UserMother::create(customer: CustomerMother::create());
        $customer = $owner->customer();
        $this->assertNotNull($customer);

        return new CustomView(
            id: CustomViewId::fromInt(value: 1),
            user: $owner,
            customer: $customer,
            title: CustomViewTitle::fromString(value: 'A view'),
            description: null,
            type: $type,
            columns: ViewColumns::fromList(columns: $columns),
            filters: ViewFilters::fromList(filters: $filters),
            isDefault: false,
            isPublic: false,
        );
    }
}
