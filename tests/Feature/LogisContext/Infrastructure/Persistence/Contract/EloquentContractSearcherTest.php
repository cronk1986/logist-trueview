<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\EloquentContractSearcher;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\SearchCriteriaMother;

final class EloquentContractSearcherTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentContractSearcher $searcher;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searcher = new EloquentContractSearcher();
        $this->customer = CustomerModel::factory()->create();
    }

    public function test_it_matches_the_name_and_maps_the_search_fields(): void
    {
        $contract = $this->contract(name: 'Zebra TrueSupport');
        $this->contract(name: 'Honeywell Depot Repair');

        $result = $this->searcher->search(criteria: $this->criteria(term: 'truesupport'));

        $this->assertSame([['id' => $contract->id, 'name' => 'Zebra TrueSupport', 'status' => 'active']], $result->results());
        $this->assertSame(1, $result->total());
    }

    public function test_it_ignores_inactive_contracts(): void
    {
        ContractModel::factory()->inactive()->create(attributes: ['customer_id' => $this->customer->id, 'name' => 'Zebra TrueSupport']);

        $this->assertSame(0, $this->searcher->search(criteria: $this->criteria(term: 'zebra'))->total());
    }

    public function test_it_only_returns_contracts_of_the_visible_customers(): void
    {
        $visible = $this->contract(name: 'Zebra TrueSupport');
        ContractModel::factory()->create(attributes: ['customer_id' => CustomerModel::factory()->create()->id, 'name' => 'Zebra TrueSupport']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([$visible->id], array_column(array: $result->results(), column_key: 'id'));
    }

    public function test_it_returns_the_newest_contracts_up_to_the_limit_and_counts_them_all(): void
    {
        $ids = [];
        foreach (range(start: 1, end: 3) as $number) {
            $ids[] = $this->contract(name: 'Zebra plan ' . $number)->id;
        }

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra', limit: 2));

        $this->assertSame([$ids[2], $ids[1]], array_column(array: $result->results(), column_key: 'id'));
        $this->assertSame(3, $result->total());
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $percent = $this->contract(name: 'Coverage 100%');
        $this->contract(name: 'Coverage 1000');

        $this->assertSame([$percent->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: '100%'))->results(), column_key: 'id'));
    }

    public function test_it_runs_a_fixed_number_of_queries_whatever_the_number_of_matches(): void
    {
        ContractModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id]);

        $queries = $this->countQueries(callback: fn () => $this->searcher->search(criteria: $this->criteria(term: 'r', limit: 10)));

        $this->assertSame(2, $queries);
    }

    private function contract(string $name): ContractModel
    {
        return ContractModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'name' => $name]);
    }

    private function criteria(string $term, int $limit = 5): SearchCriteria
    {
        return SearchCriteriaMother::create(term: $term, limit: $limit, customerIds: [$this->customer->id]);
    }
}
