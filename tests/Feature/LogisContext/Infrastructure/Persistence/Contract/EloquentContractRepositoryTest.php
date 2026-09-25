<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\EloquentContractRepository;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentContractRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentContractRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentContractRepository::class);
    }

    public function test_it_maps_a_contract_with_its_customer(): void
    {
        $customer = CustomerModel::factory()->create();
        $model = ContractModel::factory()->inactive()->create(attributes: ['customer_id' => $customer->id, 'name' => 'TrueSupport 0001']);

        $contract = $this->repository->findById(id: ContractId::fromInt(value: $model->id));

        $this->assertSame($model->id, $contract?->id()->value());
        $this->assertSame($customer->id, $contract->customer()->id()->value());
        $this->assertSame('TrueSupport 0001', $contract->name());
        $this->assertSame(ContractStatus::Inactive, $contract->status());
    }

    public function test_a_missing_contract_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: ContractId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $customer = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $model = ContractModel::factory()->create(attributes: ['customer_id' => $customer->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: ContractId::fromInt(value: $model->id)));

        $this->assertSame(3, $queries);
    }
}
