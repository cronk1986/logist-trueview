<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Asset;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Asset\EloquentAssetRepository;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentAssetRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentAssetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentAssetRepository::class);
    }

    public function test_it_maps_an_asset_with_its_contract_and_location(): void
    {
        $this->freezeTime();
        $model = AssetModel::factory()->withContract()->withLocation()->withStatus(status: AssetStatus::Retired)->create(attributes: [
            'serial_number' => 'SN-00000001',
            'manufacturer' => 'Zebra',
            'model' => 'TC52',
            'item_name' => 'Zebra TC52',
        ]);

        $asset = $this->repository->findById(id: AssetId::fromInt(value: $model->id));

        $this->assertNotNull($asset);
        $this->assertSame($model->id, $asset->id()->value());
        $this->assertSame($model->customer_id, $asset->customer()->id()->value());
        $this->assertSame('SN-00000001', $asset->serialNumber()->value());
        $this->assertSame('Zebra', $asset->manufacturer());
        $this->assertSame('TC52', $asset->model());
        $this->assertSame('Zebra TC52', $asset->itemName());
        $this->assertSame($model->contract_id, $asset->contract()?->id()->value());
        $this->assertSame($model->location_id, $asset->location()?->id()->value());
        $this->assertSame(AssetStatus::Retired, $asset->status());
        $this->assertSame(now()->utc()->format(format: 'Y-m-d H:i:s'), $asset->createdAt()->format(format: 'Y-m-d H:i:s'));
    }

    public function test_it_maps_an_asset_without_contract_or_location(): void
    {
        $model = AssetModel::factory()->create();

        $asset = $this->repository->findById(id: AssetId::fromInt(value: $model->id));

        $this->assertNotNull($asset);
        $this->assertNull($asset->contract());
        $this->assertNull($asset->location());
    }

    public function test_a_soft_deleted_asset_is_not_found(): void
    {
        $model = AssetModel::factory()->create();
        $model->delete();

        $this->assertNull($this->repository->findById(id: AssetId::fromInt(value: $model->id)));
    }

    public function test_a_missing_asset_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: AssetId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $customer = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $model = AssetModel::factory()->withContract()->withLocation()->create(attributes: ['customer_id' => $customer->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: AssetId::fromInt(value: $model->id)));

        $this->assertSame(9, $queries);
    }
}
