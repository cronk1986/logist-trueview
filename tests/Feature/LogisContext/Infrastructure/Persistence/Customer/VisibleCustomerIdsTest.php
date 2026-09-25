<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\VisibleCustomerIds;
use Tests\TestCase;

final class VisibleCustomerIdsTest extends TestCase
{
    use RefreshDatabase;

    private CustomerModel $parent;
    private CustomerModel $subAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = CustomerModel::factory()->create();
        $this->subAccount = CustomerModel::factory()->subAccountOf(parent: $this->parent)->create();
        $unrelated = CustomerModel::factory()->create();
        CustomerModel::factory()->subAccountOf(parent: $unrelated)->create();
    }

    public function test_none_matches_no_customer(): void
    {
        $this->assertSame([], $this->ids(customers: VisibleCustomers::none()));
    }

    public function test_the_own_customer_matches_only_itself(): void
    {
        $customers = VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->parent->id));

        $this->assertSame([$this->parent->id], $this->ids(customers: $customers));
    }

    public function test_a_customer_with_sub_accounts_matches_itself_and_its_sub_accounts(): void
    {
        $customers = VisibleCustomers::customerWithSubAccounts(customer: CustomerId::fromInt(value: $this->parent->id));

        $this->assertSame([$this->parent->id, $this->subAccount->id], $this->ids(customers: $customers));
    }

    public function test_a_sub_account_never_matches_its_parent(): void
    {
        $customers = VisibleCustomers::customerWithSubAccounts(customer: CustomerId::fromInt(value: $this->subAccount->id));

        $this->assertSame([$this->subAccount->id], $this->ids(customers: $customers));
    }

    public function test_the_bound_values_do_not_grow_with_the_number_of_sub_accounts(): void
    {
        CustomerModel::factory()->count(count: 300)->subAccountOf(parent: $this->parent)->create();
        $customers = VisibleCustomers::customerWithSubAccounts(customer: CustomerId::fromInt(value: $this->parent->id));

        $subquery = VisibleCustomerIds::of(customers: $customers);

        $this->assertCount(2, $subquery->getBindings());
        $this->assertCount(302, $subquery->pluck(column: 'id'));
    }

    /**
     * @return list<int>
     */
    private function ids(VisibleCustomers $customers): array
    {
        return array_values(array: VisibleCustomerIds::of(customers: $customers)->orderBy('id')->pluck(column: 'id')->all());
    }
}
