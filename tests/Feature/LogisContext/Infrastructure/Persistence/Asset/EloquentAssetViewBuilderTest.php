<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Asset;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Asset\EloquentAssetViewBuilder;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class EloquentAssetViewBuilderTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentAssetViewBuilder $builder;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->app->make(abstract: EloquentAssetViewBuilder::class);
        $this->customer = CustomerModel::factory()->create(attributes: ['name' => 'Logis Demo']);
    }

    public function test_every_catalog_field_has_a_column(): void
    {
        $fields = array_map(callback: fn (ViewField $field): string => $field->name(), array: ViewFieldCatalog::for(type: ViewType::MyAssets)->fields());

        $this->assertSame($fields, array_keys(array: EloquentAssetViewBuilder::fields()));
    }

    public function test_it_renders_the_requested_columns_with_relations_as_references(): void
    {
        $location = LocationModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'title' => 'Main warehouse']);
        $asset = $this->asset(attributes: [
            'serial_number' => 'ZB-1',
            'status' => AssetStatus::Retired,
            'location_id' => $location->id,
            'created_at' => CarbonImmutable::parse(time: '2024-03-01 10:15:00', timezone: 'UTC'),
        ]);

        $page = $this->execute(columns: ['serial_number', 'status', 'location', 'contract', 'customer', 'created_at']);

        $this->assertSame(
            [[
                'id' => $asset->id,
                'serial_number' => 'ZB-1',
                'status' => 'retired',
                'location' => ['id' => $location->id, 'label' => 'Main warehouse'],
                'contract' => null,
                'customer' => ['id' => $this->customer->id, 'label' => 'Logis Demo'],
                'created_at' => '2024-03-01T10:15:00Z',
            ]],
            $page->rows(),
        );
        $this->assertNull($page->nextCursor());
    }

    public function test_it_filters_text_by_equality_list_and_containment(): void
    {
        $zebra = $this->asset(attributes: ['manufacturer' => 'Zebra', 'model' => 'TC52']);
        $honeywell = $this->asset(attributes: ['manufacturer' => 'Honeywell', 'model' => 'CT40']);
        $datalogic = $this->asset(attributes: ['manufacturer' => 'Datalogic', 'model' => '50% off_x']);

        $this->assertSame([$zebra->id], $this->ids(filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'zebra']]));
        $this->assertSame([$datalogic->id, $honeywell->id], $this->ids(filters: [['column' => 'manufacturer', 'operator' => 'neq', 'value' => 'Zebra']]));
        $this->assertSame([$honeywell->id, $zebra->id], $this->ids(filters: [['column' => 'manufacturer', 'operator' => 'in', 'value' => ['Zebra', 'Honeywell']]]));
        $this->assertSame([$datalogic->id], $this->ids(filters: [['column' => 'manufacturer', 'operator' => 'not_in', 'value' => ['Zebra', 'Honeywell']]]));
        $this->assertSame([$datalogic->id], $this->ids(filters: [['column' => 'model', 'operator' => 'contains', 'value' => '0% off_']]));
        $this->assertSame([$datalogic->id], $this->ids(filters: [['column' => 'model', 'operator' => 'contains', 'value' => '%']]));
        $this->assertSame([], $this->ids(filters: [['column' => 'model', 'operator' => 'contains', 'value' => 'T_52']]));
    }

    public function test_it_filters_choices_and_identifiers(): void
    {
        $contract = ContractModel::factory()->create(attributes: ['customer_id' => $this->customer->id]);
        $active = $this->asset(attributes: ['status' => AssetStatus::Active, 'contract_id' => $contract->id]);
        $retired = $this->asset(attributes: ['status' => AssetStatus::Retired]);

        $this->assertSame([$retired->id], $this->ids(filters: [['column' => 'status', 'operator' => 'eq', 'value' => 'retired']]));
        $this->assertSame([$retired->id], $this->ids(filters: [['column' => 'status', 'operator' => 'neq', 'value' => 'active']]));
        $this->assertSame([$active->id], $this->ids(filters: [['column' => 'contract', 'operator' => 'eq', 'value' => $contract->id]]));
        $this->assertSame([$retired->id], $this->ids(filters: [['column' => 'contract', 'operator' => 'not_in', 'value' => [$contract->id]]]));
        $this->assertSame([$retired->id], $this->ids(filters: [['column' => 'id', 'operator' => 'in', 'value' => [$retired->id]]]));
    }

    public function test_it_filters_dates_by_whole_utc_days(): void
    {
        $lastOfJanuary = $this->asset(attributes: ['created_at' => CarbonImmutable::parse(time: '2024-01-31 23:59:59', timezone: 'UTC')]);
        $firstOfFebruary = $this->asset(attributes: ['created_at' => CarbonImmutable::parse(time: '2024-02-01 00:00:00', timezone: 'UTC')]);
        $march = $this->asset(attributes: ['created_at' => CarbonImmutable::parse(time: '2024-03-15 12:00:00', timezone: 'UTC')]);

        $this->assertSame([$march->id, $firstOfFebruary->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-01-31']]));
        $this->assertSame([$lastOfJanuary->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-01-31']]));
        $this->assertSame([$lastOfJanuary->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'lt', 'value' => '2024-02-01']]));
        $this->assertSame([$firstOfFebruary->id, $lastOfJanuary->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-31', '2024-02-01']]]));
        $this->assertSame([$march->id, $firstOfFebruary->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-02-01']]));
    }

    public function test_in_last_days_counts_from_the_start_of_today(): void
    {
        $this->travelTo(date: CarbonImmutable::parse(time: '2024-06-10 08:00:00', timezone: 'UTC'));
        $today = $this->asset(attributes: ['created_at' => CarbonImmutable::parse(time: '2024-06-10 00:00:01', timezone: 'UTC')]);
        $this->asset(attributes: ['created_at' => CarbonImmutable::parse(time: '2024-06-09 23:59:59', timezone: 'UTC')]);

        $this->assertSame([$today->id], $this->ids(filters: [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 0]]));
        $this->assertCount(2, $this->ids(filters: [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 1]]));
    }

    public function test_it_never_returns_assets_of_customers_the_user_cannot_see(): void
    {
        $mine = $this->asset(attributes: []);
        $foreign = AssetModel::factory()->create();

        $this->assertSame([$mine->id], $this->ids(filters: []));
        $this->assertSame([], $this->ids(filters: [['column' => 'id', 'operator' => 'eq', 'value' => $foreign->id]]));
        $this->assertSame([], $this->ids(filters: [['column' => 'customer', 'operator' => 'eq', 'value' => $foreign->customer_id]]));
    }

    public function test_it_excludes_deleted_assets(): void
    {
        $kept = $this->asset(attributes: []);
        $this->asset(attributes: [])->delete();

        $this->assertSame([$kept->id], $this->ids(filters: []));
    }

    public function test_it_reads_only_the_columns_it_needs(): void
    {
        $this->asset(attributes: []);

        DB::enableQueryLog();
        $this->execute(columns: ['serial_number', 'location']);
        $query = DB::getQueryLog()[0]['query'] ?? '';
        DB::disableQueryLog();

        $this->assertStringStartsWith('select `id`, `serial_number`, `location_id` from `assets`', $query);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        $columns = ['serial_number', 'location', 'contract', 'customer'];
        AssetModel::factory()->withLocation()->withContract()->create(attributes: ['customer_id' => $this->customer->id]);
        $withOne = $this->countQueries(callback: fn () => $this->execute(columns: $columns));

        AssetModel::factory()->count(count: 20)->withLocation()->withContract()->create(attributes: ['customer_id' => $this->customer->id]);
        $withMany = $this->countQueries(callback: fn () => $this->execute(columns: $columns));

        $this->assertSame(4, $withOne);
        $this->assertSame($withOne, $withMany);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function asset(array $attributes): AssetModel
    {
        return AssetModel::factory()->create(attributes: ['customer_id' => $this->customer->id, ...$attributes]);
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function execute(array $columns = ['id'], array $filters = []): ViewPage
    {
        return $this->builder->execute(criteria: ViewExecutionCriteriaMother::create(
            type: ViewType::MyAssets,
            customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->customer->id)),
            columns: $columns,
            filters: $filters,
        ));
    }

    /**
     * @param list<array<string, mixed>> $filters
     * @return list<mixed>
     */
    private function ids(array $filters): array
    {
        return array_column(array: $this->execute(filters: $filters)->rows(), column_key: 'id');
    }
}
