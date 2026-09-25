<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Ticket;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\EloquentTicketSearcher;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Query\GlobalSearch\SearchCriteriaMother;

final class EloquentTicketSearcherTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentTicketSearcher $searcher;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searcher = new EloquentTicketSearcher();
        $this->customer = CustomerModel::factory()->create();
    }

    public function test_it_matches_the_subject_the_repair_id_and_the_incident_number(): void
    {
        $bySubject = $this->ticket(attributes: ['subject' => 'Zebra printer jams']);
        $byRepairId = $this->ticket(attributes: ['repair_id' => 'R-ZEBRA-1']);
        $byIncident = $this->ticket(attributes: ['incident_number' => 'INC-zebra-9']);
        $this->ticket(attributes: ['subject' => 'Honeywell scanner']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([$byIncident->id, $byRepairId->id, $bySubject->id], array_column(array: $result->results(), column_key: 'id'));
        $this->assertSame(3, $result->total());
    }

    public function test_it_maps_the_search_fields_and_the_status_name(): void
    {
        $status = TicketStatusModel::factory()->create(attributes: ['name' => 'Awaiting Parts']);
        $ticket = $this->ticket(attributes: [
            'subject' => 'Zebra printer jams',
            'repair_id' => 'R-10231',
            'incident_number' => 'INC-5521',
            'status_id' => $status->id,
        ]);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([[
            'id' => $ticket->id,
            'repair_id' => 'R-10231',
            'incident_number' => 'INC-5521',
            'subject' => 'Zebra printer jams',
            'status' => 'Awaiting Parts',
        ]], $result->results());
    }

    public function test_it_only_returns_tickets_of_the_visible_customers(): void
    {
        $other = CustomerModel::factory()->create();
        $visible = $this->ticket(attributes: ['subject' => 'Zebra printer']);
        TicketModel::factory()->create(attributes: ['customer_id' => $other->id, 'subject' => 'Zebra printer']);

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([$visible->id], array_column(array: $result->results(), column_key: 'id'));
        $this->assertSame(1, $result->total());
    }

    public function test_it_ignores_soft_deleted_tickets(): void
    {
        $this->ticket(attributes: ['subject' => 'Zebra printer'])->delete();

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra'));

        $this->assertSame([], $result->results());
        $this->assertSame(0, $result->total());
    }

    public function test_it_returns_the_newest_tickets_up_to_the_limit_and_counts_them_all(): void
    {
        $ids = [];
        foreach (range(start: 1, end: 4) as $ignored) {
            $ids[] = $this->ticket(attributes: ['subject' => 'Zebra printer'])->id;
        }

        $result = $this->searcher->search(criteria: $this->criteria(term: 'zebra', limit: 2));

        $this->assertSame([$ids[3], $ids[2]], array_column(array: $result->results(), column_key: 'id'));
        $this->assertSame(4, $result->total());
    }

    public function test_like_wildcards_in_the_term_are_matched_literally(): void
    {
        $percent = $this->ticket(attributes: ['subject' => 'Discount of 50% applied']);
        $this->ticket(attributes: ['subject' => 'Discount of 500 applied']);
        $underscore = $this->ticket(attributes: ['subject' => 'Error in field_name']);
        $this->ticket(attributes: ['subject' => 'Error in fieldXname']);

        $this->assertSame([$percent->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: '50%'))->results(), column_key: 'id'));
        $this->assertSame([$underscore->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: 'field_name'))->results(), column_key: 'id'));
        $this->assertSame([$percent->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: '%'))->results(), column_key: 'id'));
    }

    public function test_a_backslash_in_the_term_is_matched_literally(): void
    {
        $withBackslash = $this->ticket(attributes: ['subject' => 'Log saved in C:\\temp']);
        $this->ticket(attributes: ['subject' => 'Log saved in C:temp']);

        $this->assertSame([$withBackslash->id], array_column(array: $this->searcher->search(criteria: $this->criteria(term: 'C:\\t'))->results(), column_key: 'id'));
    }

    public function test_it_runs_one_query_when_nothing_matches(): void
    {
        $this->ticket(attributes: ['subject' => 'Honeywell scanner']);

        $queries = $this->countQueries(callback: fn () => $this->searcher->search(criteria: $this->criteria(term: 'zebra')));

        $this->assertSame(1, $queries);
    }

    public function test_it_runs_a_fixed_number_of_queries_whatever_the_number_of_matches(): void
    {
        TicketModel::factory()->count(count: 10)->create(attributes: ['customer_id' => $this->customer->id, 'subject' => 'Zebra printer']);

        $queries = $this->countQueries(callback: fn () => $this->searcher->search(criteria: $this->criteria(term: 'zebra', limit: 10)));

        $this->assertSame(3, $queries);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function ticket(array $attributes): TicketModel
    {
        return TicketModel::factory()->create(attributes: ['customer_id' => $this->customer->id, ...$attributes]);
    }

    private function criteria(string $term, int $limit = 5): SearchCriteria
    {
        return SearchCriteriaMother::create(term: $term, limit: $limit, customerId: $this->customer->id);
    }
}
