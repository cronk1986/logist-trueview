<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\TicketStatus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\TicketStatus\TicketStatusId;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\EloquentTicketStatusRepository;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Tests\TestCase;

final class EloquentTicketStatusRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTicketStatusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentTicketStatusRepository::class);
    }

    public function test_it_maps_a_ticket_status(): void
    {
        $model = TicketStatusModel::factory()->create(attributes: ['name' => 'Awaiting Parts']);

        $status = $this->repository->findById(id: TicketStatusId::fromInt(value: $model->id));

        $this->assertSame($model->id, $status?->id()->value());
        $this->assertSame('Awaiting Parts', $status->name());
    }

    public function test_a_missing_ticket_status_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: TicketStatusId::fromInt(value: 999)));
    }
}
