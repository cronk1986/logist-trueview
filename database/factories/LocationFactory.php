<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;

/**
 * @extends Factory<LocationModel>
 */
final class LocationFactory extends Factory
{
    /** @var class-string<LocationModel> */
    protected $model = LocationModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => CustomerModel::factory(),
            'title' => fake()->city() . ' Warehouse',
            'full_address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['California', 'Texas', 'New York', 'Florida']),
            'postal_code' => fake()->postcode(),
        ];
    }

    public function withoutAddress(): self
    {
        return $this->state(state: ['full_address' => null, 'city' => null, 'state' => null, 'postal_code' => null]);
    }
}
