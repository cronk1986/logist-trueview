<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(class: TicketStatusSeeder::class);

        if (!app()->environment('local', 'testing')) {
            return;
        }

        UserModel::factory()->create(attributes: [
            'name' => 'Demo',
            'email' => 'demo@logis.test',
            'password' => 'password',
        ]);
    }
}
