<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\TicketStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Tests\TestCase;

final class TicketStatusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_every_status_once_even_when_run_twice(): void
    {
        $this->seed(class: TicketStatusSeeder::class);
        $this->seed(class: TicketStatusSeeder::class);

        $this->assertSame(
            TicketStatusSeeder::STATUSES,
            TicketStatusModel::query()->orderBy('id')->pluck(column: 'name')->all(),
        );
    }
}
