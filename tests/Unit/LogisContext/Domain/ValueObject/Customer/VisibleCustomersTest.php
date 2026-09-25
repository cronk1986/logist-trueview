<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Customer;

use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use PHPUnit\Framework\TestCase;

final class VisibleCustomersTest extends TestCase
{
    public function test_none_is_empty(): void
    {
        $customers = VisibleCustomers::none();

        $this->assertTrue($customers->isEmpty());
        $this->assertNull($customers->customer());
        $this->assertFalse($customers->includesSubAccounts());
    }

    public function test_the_own_customer_excludes_its_sub_accounts(): void
    {
        $customers = VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: 7));

        $this->assertFalse($customers->isEmpty());
        $this->assertSame(7, $customers->customer()?->value());
        $this->assertFalse($customers->includesSubAccounts());
    }

    public function test_a_customer_can_include_its_sub_accounts(): void
    {
        $customers = VisibleCustomers::customerWithSubAccounts(customer: CustomerId::fromInt(value: 7));

        $this->assertFalse($customers->isEmpty());
        $this->assertSame(7, $customers->customer()?->value());
        $this->assertTrue($customers->includesSubAccounts());
    }
}
