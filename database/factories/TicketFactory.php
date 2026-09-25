<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

/**
 * @extends Factory<TicketModel>
 */
final class TicketFactory extends Factory
{
    /** @var class-string<TicketModel> */
    protected $model = TicketModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => CustomerModel::factory(),
            'asset_id' => null,
            'repair_id' => fake()->unique()->numerify('RMA-######'),
            'incident_number' => fake()->unique()->numerify('INC-######'),
            'subject' => fake()->sentence(),
            'status_id' => TicketStatusModel::factory(),
            'issue_description' => fake()->paragraph(),
            'created_by' => fn (array $attributes): int => UserModel::factory()->create(attributes: ['customer_id' => $attributes['customer_id']])->id,
        ];
    }

    public function withAsset(): self
    {
        return $this->state(state: [
            'asset_id' => fn (array $attributes): int => AssetModel::factory()->create(attributes: ['customer_id' => $attributes['customer_id']])->id,
        ]);
    }
}
