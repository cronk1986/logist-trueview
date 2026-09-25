<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Exception\Customer\CustomerCannotBeItsOwnParentException;
use Logis\LogisContext\Domain\Exception\Customer\CustomerHierarchyTooDeepException;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function test_a_top_level_customer_has_no_parent(): void
    {
        $customer = CustomerMother::create(id: 1, erpId: 'C-1000', name: 'Logis Demo');

        $this->assertSame(1, $customer->id()->value());
        $this->assertSame('C-1000', $customer->erpId()->value());
        $this->assertSame('Logis Demo', $customer->name());
        $this->assertNull($customer->parent());
        $this->assertFalse($customer->isSubAccount());
    }

    public function test_a_sub_account_holds_its_parent(): void
    {
        $parent = CustomerMother::create(id: 1);

        $subAccount = CustomerMother::create(id: 2, erpId: 'C-1001', parent: $parent);

        $this->assertSame($parent, $subAccount->parent());
        $this->assertTrue($subAccount->isSubAccount());
    }

    public function test_a_customer_cannot_be_its_own_parent(): void
    {
        $this->expectException(CustomerCannotBeItsOwnParentException::class);

        CustomerMother::create(id: 1, parent: CustomerMother::create(id: 1));
    }

    public function test_a_sub_account_cannot_have_sub_accounts(): void
    {
        $subAccount = CustomerMother::create(id: 2, erpId: 'C-1001', parent: CustomerMother::create(id: 1));

        $this->expectException(CustomerHierarchyTooDeepException::class);

        CustomerMother::create(id: 3, erpId: 'C-1002', parent: $subAccount);
    }

    public function test_two_customers_with_the_same_id_are_the_same(): void
    {
        $this->assertTrue(CustomerMother::create(id: 1)->isSameAs(other: CustomerMother::create(id: 1, name: 'Renamed')));
        $this->assertFalse(CustomerMother::create(id: 1)->isSameAs(other: CustomerMother::create(id: 2, erpId: 'C-1001')));
    }
}
