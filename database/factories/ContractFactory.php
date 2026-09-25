<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;

/**
 * @extends Factory<ContractModel>
 */
final class ContractFactory extends Factory
{
    /** @var class-string<ContractModel> */
    protected $model = ContractModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => CustomerModel::factory(),
            'name' => fake()->randomElement(['TrueSupport', 'OEM Warranty', 'Depot Repair']) . ' ' . fake()->unique()->numerify('####'),
            'status' => ContractStatus::Active,
        ];
    }

    public function inactive(): self
    {
        return $this->state(state: ['status' => ContractStatus::Inactive]);
    }
}
