<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\Ticket;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\EloquentTicketRepository;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentTicketRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentTicketRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentTicketRepository::class);
    }

    public function test_it_maps_a_ticket_with_its_whole_graph(): void
    {
        $this->freezeTime();
        $status = TicketStatusModel::factory()->create(attributes: ['name' => 'In Progress']);
        $asset = AssetModel::factory()->withContract()->withLocation()->create();
        $model = TicketModel::factory()->create(attributes: [
            'customer_id' => $asset->customer_id,
            'asset_id' => $asset->id,
            'repair_id' => 'RMA-000001',
            'incident_number' => 'INC-000001',
            'subject' => 'Scanner does not turn on',
            'status_id' => $status->id,
            'issue_description' => 'The device stays off.',
        ]);

        $ticket = $this->repository->findById(id: TicketId::fromInt(value: $model->id));

        $this->assertNotNull($ticket);
        $this->assertSame($model->id, $ticket->id()->value());
        $this->assertSame($model->customer_id, $ticket->customer()->id()->value());
        $this->assertSame($asset->id, $ticket->asset()?->id()->value());
        $this->assertSame($asset->contract_id, $ticket->asset()->contract()?->id()->value());
        $this->assertSame($asset->location_id, $ticket->asset()->location()?->id()->value());
        $this->assertSame('RMA-000001', $ticket->repairId()->value());
        $this->assertSame('INC-000001', $ticket->incidentNumber()->value());
        $this->assertSame('Scanner does not turn on', $ticket->subject());
        $this->assertSame('In Progress', $ticket->status()->name());
        $this->assertSame('The device stays off.', $ticket->issueDescription());
        $this->assertSame($model->created_by, $ticket->createdBy()->id()->value());
        $this->assertSame($model->customer_id, $ticket->createdBy()->customer()?->id()->value());
        $this->assertSame(now()->utc()->format(format: 'Y-m-d H:i:s'), $ticket->createdAt()->format(format: 'Y-m-d H:i:s'));
    }

    public function test_a_ticket_keeps_its_soft_deleted_asset(): void
    {
        $model = TicketModel::factory()->withAsset()->create();
        AssetModel::query()->whereKey(id: $model->asset_id)->firstOrFail()->delete();

        $ticket = $this->repository->findById(id: TicketId::fromInt(value: $model->id));

        $this->assertSame($model->asset_id, $ticket?->asset()?->id()->value());
    }

    public function test_it_maps_a_ticket_without_an_asset(): void
    {
        $model = TicketModel::factory()->create();

        $this->assertNull($this->repository->findById(id: TicketId::fromInt(value: $model->id))?->asset());
    }

    public function test_a_soft_deleted_ticket_is_not_found(): void
    {
        $model = TicketModel::factory()->create();
        $model->delete();

        $this->assertNull($this->repository->findById(id: TicketId::fromInt(value: $model->id)));
    }

    public function test_a_missing_ticket_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: TicketId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $customer = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $asset = AssetModel::factory()->withContract()->withLocation()->create(attributes: ['customer_id' => $customer->id]);
        $model = TicketModel::factory()->create(attributes: ['customer_id' => $customer->id, 'asset_id' => $asset->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: TicketId::fromInt(value: $model->id)));

        $this->assertSame(16, $queries);
    }
}
