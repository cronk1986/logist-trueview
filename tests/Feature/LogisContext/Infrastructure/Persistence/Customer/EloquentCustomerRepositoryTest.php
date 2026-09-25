<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CorruptedCustomerHierarchyException;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\EloquentCustomerRepository;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentCustomerRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentCustomerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentCustomerRepository::class);
    }

    public function test_it_maps_a_top_level_customer(): void
    {
        $model = CustomerModel::factory()->create(attributes: ['erp_id' => 'C-1000', 'name' => 'Logis Demo']);

        $customer = $this->repository->findById(id: CustomerId::fromInt(value: $model->id));

        $this->assertNotNull($customer);
        $this->assertSame($model->id, $customer->id()->value());
        $this->assertSame('C-1000', $customer->erpId()->value());
        $this->assertSame('Logis Demo', $customer->name());
        $this->assertNull($customer->parent());
    }

    public function test_it_maps_a_sub_account_with_its_parent(): void
    {
        $parent = CustomerModel::factory()->create(attributes: ['erp_id' => 'C-1000']);
        $model = CustomerModel::factory()->subAccountOf(parent: $parent)->create();

        $customer = $this->repository->findById(id: CustomerId::fromInt(value: $model->id));

        $this->assertSame($parent->id, $customer?->parent()?->id()->value());
        $this->assertSame('C-1000', $customer->parent()->erpId()->value());
    }

    public function test_a_missing_customer_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: CustomerId::fromInt(value: 999)));
    }

    public function test_a_stored_hierarchy_deeper_than_one_level_is_reported_as_corrupted_data(): void
    {
        $subAccount = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $model = CustomerModel::factory()->create(attributes: ['parent_customer_id' => $subAccount->id]);

        $this->expectException(CorruptedCustomerHierarchyException::class);

        $this->repository->findById(id: CustomerId::fromInt(value: $model->id));
    }

    public function test_a_stored_customer_that_is_its_own_parent_is_reported_as_corrupted_data(): void
    {
        $model = CustomerModel::factory()->create();
        $model->update(attributes: ['parent_customer_id' => $model->id]);

        $this->expectException(CorruptedCustomerHierarchyException::class);

        $this->repository->findById(id: CustomerId::fromInt(value: $model->id));
    }

    public function test_it_loads_a_customer_and_its_parent_with_a_fixed_number_of_queries(): void
    {
        $parent = CustomerModel::factory()->create();
        $model = CustomerModel::factory()->subAccountOf(parent: $parent)->create();

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: CustomerId::fromInt(value: $model->id)));

        $this->assertSame(2, $queries);
    }

    public function test_it_lists_the_direct_sub_accounts_of_a_customer(): void
    {
        $parent = CustomerModel::factory()->create();
        $first = CustomerModel::factory()->subAccountOf(parent: $parent)->create();
        $second = CustomerModel::factory()->subAccountOf(parent: $parent)->create();
        $unrelated = CustomerModel::factory()->create();
        CustomerModel::factory()->subAccountOf(parent: $unrelated)->create();

        $subAccounts = $this->repository->subAccountsOf(id: CustomerId::fromInt(value: $parent->id));

        $this->assertSame([$first->id, $second->id], array_map(callback: fn ($customer): int => $customer->id()->value(), array: $subAccounts));
        $this->assertSame($parent->id, $subAccounts[0]->parent()?->id()->value());
    }

    public function test_a_customer_without_sub_accounts_has_none(): void
    {
        $customer = CustomerModel::factory()->create();

        $this->assertSame([], $this->repository->subAccountsOf(id: CustomerId::fromInt(value: $customer->id)));
    }

    public function test_it_lists_sub_accounts_with_a_fixed_number_of_queries(): void
    {
        $parent = CustomerModel::factory()->create();
        CustomerModel::factory()->count(count: 5)->subAccountOf(parent: $parent)->create();

        $queries = $this->countQueries(callback: fn () => $this->repository->subAccountsOf(id: CustomerId::fromInt(value: $parent->id)));

        $this->assertSame(2, $queries);
    }
}
