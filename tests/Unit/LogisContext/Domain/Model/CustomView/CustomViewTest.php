<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\CustomViewLinkedToAnotherCustomerException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewTest extends TestCase
{
    public function test_it_holds_its_owner_customer_and_configuration(): void
    {
        $owner = UserMother::create(id: 5, customer: CustomerMother::create(id: 1));

        $view = CustomViewMother::create(id: 2, user: $owner, isDefault: true, isPublic: true);

        $this->assertSame(2, $view->id()->value());
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
}
