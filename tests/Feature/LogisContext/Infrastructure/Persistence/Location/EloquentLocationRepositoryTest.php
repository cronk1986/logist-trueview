<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Location;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\EloquentLocationRepository;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentLocationRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentLocationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentLocationRepository::class);
    }

    public function test_it_maps_a_location_with_its_customer_and_address(): void
    {
        $model = LocationModel::factory()->create(attributes: [
            'title' => 'Madrid Warehouse',
            'full_address' => 'Calle Mayor 1',
            'city' => 'Madrid',
            'state' => 'Madrid',
            'postal_code' => '28013',
        ]);

        $location = $this->repository->findById(id: LocationId::fromInt(value: $model->id));

        $this->assertSame($model->id, $location?->id()->value());
        $this->assertSame($model->customer_id, $location->customer()->id()->value());
        $this->assertSame('Madrid Warehouse', $location->title());
        $this->assertSame('Calle Mayor 1', $location->fullAddress());
        $this->assertSame('Madrid', $location->city());
        $this->assertSame('Madrid', $location->state());
        $this->assertSame('28013', $location->postalCode());
    }

    public function test_it_maps_a_location_without_address(): void
    {
        $model = LocationModel::factory()->withoutAddress()->create();

        $location = $this->repository->findById(id: LocationId::fromInt(value: $model->id));

        $this->assertNotNull($location);
        $this->assertNull($location->fullAddress());
        $this->assertNull($location->city());
        $this->assertNull($location->state());
        $this->assertNull($location->postalCode());
    }

    public function test_a_missing_location_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: LocationId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $customer = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $model = LocationModel::factory()->create(attributes: ['customer_id' => $customer->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: LocationId::fromInt(value: $model->id)));

        $this->assertSame(3, $queries);
    }
}
