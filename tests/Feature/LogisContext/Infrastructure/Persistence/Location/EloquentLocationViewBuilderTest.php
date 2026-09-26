<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Location;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\EloquentLocationViewBuilder;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class EloquentLocationViewBuilderTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentLocationViewBuilder $builder;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new EloquentLocationViewBuilder(paginator: $this->app->make(abstract: ViewPaginator::class));
        $this->customer = CustomerModel::factory()->create();
    }

    public function test_every_catalog_field_has_a_column(): void
    {
        $fields = array_map(callback: fn (ViewField $field): string => $field->name(), array: ViewFieldCatalog::for(type: ViewType::MyLocations)->fields());

        $this->assertSame($fields, array_keys(array: EloquentLocationViewBuilder::fields()));
    }

    public function test_null_address_fields_are_rendered_as_null(): void
    {
        $location = LocationModel::factory()->withoutAddress()->create(attributes: ['customer_id' => $this->customer->id, 'title' => 'Depot']);

        $this->assertSame(
            [['id' => $location->id, 'title' => 'Depot', 'city' => null, 'state' => null, 'postal_code' => null]],
            $this->execute(columns: ['title', 'city', 'state', 'postal_code'])->rows(),
        );
    }

    public function test_negative_filters_keep_locations_without_a_value(): void
    {
        $austin = $this->location(attributes: ['city' => 'Austin']);
        $dallas = $this->location(attributes: ['city' => 'Dallas']);
        $unknown = LocationModel::factory()->withoutAddress()->create(attributes: ['customer_id' => $this->customer->id]);

        $this->assertSame([$unknown->id, $dallas->id], $this->ids(filters: [['column' => 'city', 'operator' => 'neq', 'value' => 'Austin']]));
        $this->assertSame([$unknown->id], $this->ids(filters: [['column' => 'city', 'operator' => 'not_in', 'value' => ['Austin', 'Dallas']]]));
        $this->assertSame([$austin->id], $this->ids(filters: [['column' => 'city', 'operator' => 'eq', 'value' => 'Austin']]));
    }

    public function test_the_search_term_matches_the_address(): void
    {
        $match = $this->location(attributes: ['postal_code' => '73301']);
        $this->location(attributes: ['postal_code' => '10001']);

        $this->assertSame([$match->id], array_column(array: $this->execute(term: '733')->rows(), column_key: 'id'));
    }

    public function test_it_never_returns_locations_of_other_customers(): void
    {
        $mine = $this->location(attributes: []);
        LocationModel::factory()->create();

        $this->assertSame([$mine->id], $this->ids(filters: []));
    }

    public function test_the_number_of_queries_is_fixed(): void
    {
        $this->location(attributes: []);
        $withOne = $this->countQueries(callback: fn () => $this->execute(columns: ['title', 'customer']));

        LocationModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id]);

        $this->assertSame(2, $withOne);
        $this->assertSame($withOne, $this->countQueries(callback: fn () => $this->execute(columns: ['title', 'customer'])));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function location(array $attributes): LocationModel
    {
        return LocationModel::factory()->create(attributes: ['customer_id' => $this->customer->id, ...$attributes]);
    }

    /**
     * @param list<array<string, mixed>> $filters
     * @return list<mixed>
     */
    private function ids(array $filters): array
    {
        return array_column(array: $this->execute(filters: $filters)->rows(), column_key: 'id');
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function execute(array $columns = ['id'], array $filters = [], ?string $term = null): ViewPage
    {
        return $this->builder->execute(criteria: ViewExecutionCriteriaMother::create(
            type: ViewType::MyLocations,
            customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->customer->id)),
            columns: $columns,
            filters: $filters,
            term: $term,
        ));
    }
}
