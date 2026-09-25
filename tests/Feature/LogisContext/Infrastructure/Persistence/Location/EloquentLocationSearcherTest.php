<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Location;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\EloquentLocationSearcher;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\SearchCriteriaMother;

final class EloquentLocationSearcherTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentLocationSearcher $searcher;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searcher = new EloquentLocationSearcher();
        $this->customer = CustomerModel::factory()->create();
    }

    public function test_it_matches_the_title_and_every_address_field(): void
    {
        $byTitle = $this->location(attributes: ['title' => 'Springfield depot']);
        $byAddress = $this->location(attributes: ['full_address' => '742 Springfield Road']);
        $byCity = $this->location(attributes: ['city' => 'Springfield']);
        $byState = $this->location(attributes: ['state' => 'springfield county']);
        $byPostalCode = $this->location(attributes: ['postal_code' => 'SPRINGFIELD-1']);
        $this->location(attributes: ['title' => 'Shelbyville depot']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'springfield'));

        $this->assertSame(
            [$byPostalCode->id, $byState->id, $byCity->id, $byAddress->id, $byTitle->id],
            array_column(array: $result->results(), column_key: 'id'),
        );
        $this->assertSame(5, $result->total());
    }

    public function test_it_maps_the_search_fields(): void
    {
        $location = $this->location(attributes: ['title' => 'Austin Warehouse', 'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '73301']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'austin'));

        $this->assertSame([['id' => $location->id, 'title' => 'Austin Warehouse', 'city' => 'Austin', 'state' => 'Texas', 'postal_code' => '73301']], $result->results());
    }

    public function test_a_location_without_address_is_found_by_its_title(): void
    {
        $location = LocationModel::factory()->withoutAddress()->create(attributes: ['customer_id' => $this->customer->id, 'title' => 'Mobile unit']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'mobile'));

        $this->assertSame([['id' => $location->id, 'title' => 'Mobile unit', 'city' => null, 'state' => null, 'postal_code' => null]], $result->results());
    }

    public function test_it_only_returns_locations_of_the_visible_customers(): void
    {
        $visible = $this->location(attributes: ['title' => 'Springfield depot']);
        LocationModel::factory()->create(attributes: ['customer_id' => CustomerModel::factory()->create()->id, 'title' => 'Springfield depot']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'springfield'));

        $this->assertSame([$visible->id], array_column(array: $result->results(), column_key: 'id'));
    }

    public function test_it_returns_the_newest_locations_up_to_the_limit_and_counts_them_all(): void
    {
        $ids = [];
        foreach (range(start: 1, end: 3) as $ignored) {
            $ids[] = $this->location(attributes: ['title' => 'Springfield depot'])->id;
        }

        $result = $this->searcher->search(criteria: $this->criteria(term: 'springfield', limit: 2));

        $this->assertSame([$ids[2], $ids[1]], array_column(array: $result->results(), column_key: 'id'));
        $this->assertSame(3, $result->total());
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $underscore = $this->location(attributes: ['title' => 'Dock_A']);
        $this->location(attributes: ['title' => 'DockBA']);

        $this->assertSame([$underscore->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: 'dock_'))->results(), column_key: 'id'));
    }

    public function test_it_runs_a_fixed_number_of_queries_whatever_the_number_of_matches(): void
    {
        LocationModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id, 'title' => 'Springfield depot']);

        $queries = $this->countQueries(callback: fn () => $this->searcher->search(criteria: $this->criteria(term: 'springfield', limit: 10)));

        $this->assertSame(2, $queries);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function location(array $attributes): LocationModel
    {
        return LocationModel::factory()->create(attributes: [
            'customer_id' => $this->customer->id,
            'title' => 'Warehouse',
            'full_address' => '1 Main Street',
            'city' => 'Metropolis',
            'state' => 'Nevada',
            'postal_code' => '00000',
            ...$attributes,
        ]);
    }

    private function criteria(string $term, int $limit = 5): SearchCriteria
    {
        return SearchCriteriaMother::create(term: $term, limit: $limit, customerId: $this->customer->id);
    }
}
