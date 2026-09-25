<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;

final class TicketStatusSeeder extends Seeder
{
    public const STATUSES = ['Open', 'In Progress', 'Awaiting Parts', 'Resolved', 'Closed'];

    public function run(): void
    {
        $now = now();
        $rows = array_map(callback: fn (string $name): array => ['name' => $name, 'created_at' => $now, 'updated_at' => $now], array: self::STATUSES);

        TicketStatusModel::query()->upsert(values: $rows, uniqueBy: ['name'], update: ['updated_at']);
    }
}
