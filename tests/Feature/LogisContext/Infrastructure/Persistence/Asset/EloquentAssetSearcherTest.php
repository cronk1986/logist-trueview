<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Asset;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Asset\EloquentAssetSearcher;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\SearchCriteriaMother;

final class EloquentAssetSearcherTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentAssetSearcher $searcher;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searcher = new EloquentAssetSearcher();
        $this->customer = CustomerModel::factory()->create();
    }

    public function test_it_matches_the_serial_number_the_manufacturer_and_the_model(): void
    {
        $bySerialNumber = $this->asset(attributes: ['serial_number' => 'SN-ZEBRA-01']);
        $byManufacturer = $this->asset(attributes: ['manufacturer' => 'Zebra']);
        $byModel = $this->asset(attributes: ['model' => 'zebra-tc52']);
        $this->asset(attributes: ['item_name' => 'Zebra printer']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'ZEBRA'));

        $this->assertSame([$byModel->id, $byManufacturer->id, $bySerialNumber->id], $this->ids(results: $result->results()));
        $this->assertSame(3, $result->total());
    }

    public function test_it_maps_the_search_fields(): void
    {
        $asset = $this->asset(attributes: [
            'serial_number' => 'ZB-00931',
            'manufacturer' => 'Zebra',
            'model' => 'ZT410',
            'item_name' => 'Label printer',
            'status' => AssetStatus::Inactive,
        ]);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([[
            'id' => $asset->id,
            'serial_number' => 'ZB-00931',
            'manufacturer' => 'Zebra',
            'model' => 'ZT410',
            'item_name' => 'Label printer',
            'status' => 'inactive',
        ]], $result->results());
    }

    public function test_it_returns_assets_of_every_status(): void
    {
        foreach (AssetStatus::cases() as $status) {
            $this->asset(attributes: ['manufacturer' => 'Zebra', 'status' => $status]);
        }

        $this->assertSame(count(value: AssetStatus::cases()), $this->searcher->search(criteria: $this->criteria(term: 'zebra'))->total());
    }

    public function test_it_only_returns_assets_of_the_visible_customers(): void
    {
        $visible = $this->asset(attributes: ['manufacturer' => 'Zebra']);
        AssetModel::factory()->create(attributes: ['customer_id' => CustomerModel::factory()->create()->id, 'manufacturer' => 'Zebra', 'model' => 'X1']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([$visible->id], $this->ids(results: $result->results()));
    }

    public function test_it_ignores_soft_deleted_assets(): void
    {
        $this->asset(attributes: ['manufacturer' => 'Zebra'])->delete();

        $this->assertSame(0, $this->searcher->search(criteria: $this->criteria(term: 'zebra'))->total());
    }

    public function test_it_returns_the_newest_assets_up_to_the_limit_and_counts_them_all(): void
    {
        $ids = [];
        foreach (range(start: 1, end: 3) as $ignored) {
            $ids[] = $this->asset(attributes: ['manufacturer' => 'Zebra'])->id;
        }

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra', limit: 2));

        $this->assertSame([$ids[2], $ids[1]], $this->ids(results: $result->results()));
        $this->assertSame(3, $result->total());
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $underscore = $this->asset(attributes: ['serial_number' => 'SN_001']);
        $this->asset(attributes: ['serial_number' => 'SNX001']);

        $this->assertSame([$underscore->id], $this->ids(results: $this->searcher->search(criteria: $this->criteria(term: 'SN_0'))->results()));
    }

    public function test_it_runs_a_fixed_number_of_queries_whatever_the_number_of_matches(): void
    {
        AssetModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id, 'manufacturer' => 'Zebra']);

        $queries = $this->countQueries(callback: fn () => $this->searcher->search(criteria: $this->criteria(term: 'zebra', limit: 10)));

        $this->assertSame(2, $queries);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function asset(array $attributes): AssetModel
    {
        return AssetModel::factory()->create(attributes: [
            'customer_id' => $this->customer->id,
            'manufacturer' => 'Acme',
            'model' => 'X1',
            ...$attributes,
        ]);
    }

    private function criteria(string $term, int $limit = 5): SearchCriteria
    {
        return SearchCriteriaMother::create(term: $term, limit: $limit, customerIds: [$this->customer->id]);
    }

    /**
     * @param list<array<string, scalar|null>> $results
     * @return list<mixed>
     */
    private function ids(array $results): array
    {
        return array_column(array: $results, column_key: 'id');
    }
}
