<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(class: TicketStatusSeeder::class);

        if (!app()->environment('local', 'testing')) {
            return;
        }

        $this->call(class: DemoDataSeeder::class);
    }
}
