<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\User;

use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class UserTest extends TestCase
{
    public function test_it_exposes_its_data(): void
    {
        $customer = CustomerMother::create();
        $user = UserMother::create(id: 7, name: 'Demo', email: 'demo@logis.test', customer: $customer);

        $this->assertSame(7, $user->id()->value());
        $this->assertSame('Demo', $user->name());
        $this->assertSame('demo@logis.test', $user->email()->value());
        $this->assertSame($customer, $user->customer());
    }

    public function test_a_user_cannot_view_sub_accounts_by_default(): void
    {
        $this->assertFalse(UserMother::create()->canViewSubAccounts());
    }

    public function test_a_user_can_be_granted_access_to_sub_accounts(): void
    {
        $this->assertTrue(UserMother::create(canViewSubAccounts: true)->canViewSubAccounts());
    }
}
