<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;

/**
 * @extends Factory<TicketStatusModel>
 */
final class TicketStatusFactory extends Factory
{
    /** @var class-string<TicketStatusModel> */
    protected $model = TicketStatusModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
