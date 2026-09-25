<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Model\Customer\VisibleCustomersResolver;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class VisibleCustomersResolverTest extends TestCase
{
    private InMemoryCustomerRepository $customers;
    private VisibleCustomersResolver $resolver;

    protected function setUp(): void
    {
        $this->customers = new InMemoryCustomerRepository();
        $this->resolver = new VisibleCustomersResolver(customers: $this->customers);

        $parent = CustomerMother::create(id: 1, erpId: 'C-1000');
        $this->customers->add(customer: $parent);
        $this->customers->add(customer: CustomerMother::create(id: 2, erpId: 'C-1001', parent: $parent));
        $this->customers->add(customer: CustomerMother::create(id: 3, erpId: 'C-1002', parent: $parent));
        $this->customers->add(customer: CustomerMother::create(id: 4, erpId: 'C-2000'));
    }

    public function test_a_user_without_a_customer_sees_no_customer(): void
    {
        $visible = $this->resolver->for(user: UserMother::create(customer: null, canViewSubAccounts: true));

        $this->assertTrue($visible->isEmpty());
    }

    public function test_a_user_without_the_permission_sees_only_their_customer(): void
    {
        $user = UserMother::create(customer: CustomerMother::create(id: 1));

        $this->assertSame([1], $this->resolver->for(user: $user)->values());
    }

    public function test_a_user_with_the_permission_also_sees_the_sub_accounts(): void
    {
        $user = UserMother::create(customer: CustomerMother::create(id: 1), canViewSubAccounts: true);

        $this->assertSame([1, 2, 3], $this->resolver->for(user: $user)->values());
    }

    public function test_a_customer_without_sub_accounts_sees_only_itself_even_with_the_permission(): void
    {
        $user = UserMother::create(customer: CustomerMother::create(id: 4, erpId: 'C-2000'), canViewSubAccounts: true);

        $this->assertSame([4], $this->resolver->for(user: $user)->values());
    }
}
