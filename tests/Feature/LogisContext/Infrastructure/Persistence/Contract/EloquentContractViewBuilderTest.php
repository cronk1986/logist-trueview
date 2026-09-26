<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\EloquentContractViewBuilder;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class EloquentContractViewBuilderTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentContractViewBuilder $builder;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->app->make(abstract: EloquentContractViewBuilder::class);
        $this->customer = CustomerModel::factory()->create(attributes: ['name' => 'Logis Demo']);
    }

    public function test_every_catalog_field_has_a_column(): void
    {
        $fields = array_map(callback: fn (ViewField $field): string => $field->name(), array: ViewFieldCatalog::for(type: ViewType::MyContracts)->fields());

        $this->assertSame($fields, array_keys(array: EloquentContractViewBuilder::fields()));
    }

    public function test_it_returns_contracts_of_every_status(): void
    {
        $active = $this->contract(attributes: ['name' => 'TrueSupport 1']);
        $inactive = ContractModel::factory()->inactive()->create(attributes: ['customer_id' => $this->customer->id, 'name' => 'OEM 2']);

        $this->assertSame(
            [
                ['id' => $inactive->id, 'name' => 'OEM 2', 'status' => 'inactive', 'customer' => ['id' => $this->customer->id, 'label' => 'Logis Demo']],
                ['id' => $active->id, 'name' => 'TrueSupport 1', 'status' => 'active', 'customer' => ['id' => $this->customer->id, 'label' => 'Logis Demo']],
            ],
            $this->execute(columns: ['name', 'status', 'customer'])->rows(),
        );
    }

    public function test_it_filters_by_status_and_searches_by_name(): void
    {
        $this->contract(attributes: ['name' => 'Depot Repair 7']);
        $inactive = ContractModel::factory()->inactive()->create(attributes: ['customer_id' => $this->customer->id, 'name' => 'Depot Repair 8']);

        $this->assertSame([$inactive->id], array_column(array: $this->execute(filters: [['column' => 'status', 'operator' => 'eq', 'value' => 'inactive']], term: 'depot')->rows(), column_key: 'id'));
    }

    public function test_it_never_returns_contracts_of_other_customers(): void
    {
        $mine = $this->contract(attributes: []);
        ContractModel::factory()->create();

        $this->assertSame([$mine->id], array_column(array: $this->execute()->rows(), column_key: 'id'));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        $this->contract(attributes: []);
        $withOne = $this->countQueries(callback: fn () => $this->execute(columns: ['customer']));

        ContractModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id]);

        $this->assertSame(2, $withOne);
        $this->assertSame($withOne, $this->countQueries(callback: fn () => $this->execute(columns: ['customer'])));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function contract(array $attributes): ContractModel
    {
        return ContractModel::factory()->create(attributes: ['customer_id' => $this->customer->id, ...$attributes]);
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function execute(array $columns = ['id'], array $filters = [], ?string $term = null): ViewPage
    {
        return $this->builder->execute(criteria: ViewExecutionCriteriaMother::create(
            type: ViewType::MyContracts,
            customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->customer->id)),
            columns: $columns,
            filters: $filters,
            term: $term,
        ));
    }
}
