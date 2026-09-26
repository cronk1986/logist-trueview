<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Ticket;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldCatalog;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\EloquentTicketViewBuilder;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class EloquentTicketViewBuilderTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentTicketViewBuilder $builder;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new EloquentTicketViewBuilder(paginator: $this->app->make(abstract: ViewPaginator::class));
        $this->customer = CustomerModel::factory()->create(attributes: ['name' => 'Logis Demo']);
    }

    public function test_every_catalog_field_has_a_column(): void
    {
        $fields = array_map(callback: fn (ViewField $field): string => $field->name(), array: ViewFieldCatalog::for(type: ViewType::MyTickets)->fields());

        $this->assertSame($fields, array_keys(array: EloquentTicketViewBuilder::fields()));
    }

    public function test_it_renders_the_status_and_the_asset_as_references(): void
    {
        $status = TicketStatusModel::factory()->create(attributes: ['name' => 'Awaiting Parts']);
        $asset = AssetModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'serial_number' => 'ZB-9']);
        $ticket = $this->ticket(attributes: ['status_id' => $status->id, 'asset_id' => $asset->id, 'repair_id' => 'RMA-1', 'subject' => 'Printer jams']);
        $withoutAsset = $this->ticket(attributes: ['status_id' => $status->id]);

        $rows = $this->execute(columns: ['repair_id', 'subject', 'status', 'asset'])->rows();

        $this->assertSame(
            ['id' => $ticket->id, 'repair_id' => 'RMA-1', 'subject' => 'Printer jams', 'status' => ['id' => $status->id, 'label' => 'Awaiting Parts'], 'asset' => ['id' => $asset->id, 'label' => 'ZB-9']],
            $rows[1],
        );
        $this->assertSame($withoutAsset->id, $rows[0]['id']);
        $this->assertNull($rows[0]['asset']);
    }

    public function test_a_deleted_asset_is_still_shown_on_its_ticket(): void
    {
        $asset = AssetModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'serial_number' => 'ZB-OLD']);
        $this->ticket(attributes: ['asset_id' => $asset->id]);
        $asset->delete();

        $this->assertSame(['id' => $asset->id, 'label' => 'ZB-OLD'], $this->execute(columns: ['asset'])->rows()[0]['asset']);
    }

    public function test_it_filters_by_status_and_text(): void
    {
        $open = TicketStatusModel::factory()->create(attributes: ['name' => 'Open']);
        $closed = TicketStatusModel::factory()->create(attributes: ['name' => 'Closed']);
        $printer = $this->ticket(attributes: ['status_id' => $open->id, 'subject' => 'Printer jams']);
        $this->ticket(attributes: ['status_id' => $closed->id, 'subject' => 'Printer offline']);
        $this->ticket(attributes: ['status_id' => $open->id, 'subject' => 'Scanner broken']);

        $ids = array_column(array: $this->execute(filters: [
            ['column' => 'status', 'operator' => 'eq', 'value' => $open->id],
            ['column' => 'subject', 'operator' => 'contains', 'value' => 'printer'],
        ])->rows(), column_key: 'id');

        $this->assertSame([$printer->id], $ids);
    }

    public function test_it_excludes_deleted_tickets_and_other_customers(): void
    {
        $kept = $this->ticket(attributes: []);
        $this->ticket(attributes: [])->delete();
        TicketModel::factory()->create();

        $this->assertSame([$kept->id], array_column(array: $this->execute()->rows(), column_key: 'id'));
    }

    public function test_the_search_term_matches_subjects_and_identifiers(): void
    {
        $ticket = $this->ticket(attributes: ['incident_number' => 'INC-4242']);
        $this->ticket(attributes: []);

        $this->assertSame([$ticket->id], array_column(array: $this->execute(term: 'inc-4242')->rows(), column_key: 'id'));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        $columns = ['status', 'asset', 'customer'];
        TicketModel::factory()->withAsset()->create(attributes: ['customer_id' => $this->customer->id]);
        $withOne = $this->countQueries(callback: fn () => $this->execute(columns: $columns));

        TicketModel::factory()->count(count: 15)->withAsset()->create(attributes: ['customer_id' => $this->customer->id]);

        $this->assertSame(4, $withOne);
        $this->assertSame($withOne, $this->countQueries(callback: fn () => $this->execute(columns: $columns)));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function ticket(array $attributes): TicketModel
    {
        return TicketModel::factory()->create(attributes: ['customer_id' => $this->customer->id, ...$attributes]);
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function execute(array $columns = ['id'], array $filters = [], ?string $term = null): ViewPage
    {
        return $this->builder->execute(criteria: ViewExecutionCriteriaMother::create(
            type: ViewType::MyTickets,
            customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->customer->id)),
            columns: $columns,
            filters: $filters,
            term: $term,
        ));
    }
}
