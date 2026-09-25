<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;

/**
 * @extends Factory<AssetModel>
 */
final class AssetFactory extends Factory
{
    private const CATALOG = [
        'Zebra' => ['TC52', 'TC57', 'ZT410', 'MC3300'],
        'Honeywell' => ['CT40', 'CK65', 'EDA51'],
        'Datalogic' => ['Memor 10', 'Skorpio X5'],
    ];

    /** @var class-string<AssetModel> */
    protected $model = AssetModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $manufacturer = (string) fake()->randomElement(array_keys(array: self::CATALOG));
        $model = (string) fake()->randomElement(self::CATALOG[$manufacturer]);

        return [
            'customer_id' => CustomerModel::factory(),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'manufacturer' => $manufacturer,
            'model' => $model,
            'item_name' => $manufacturer . ' ' . $model,
            'contract_id' => null,
            'location_id' => null,
            'status' => AssetStatus::Active,
        ];
    }

    public function withContract(): self
    {
        return $this->state(state: [
            'contract_id' => fn (array $attributes): int => ContractModel::factory()->create(attributes: ['customer_id' => $attributes['customer_id']])->id,
        ]);
    }

    public function withLocation(): self
    {
        return $this->state(state: [
            'location_id' => fn (array $attributes): int => LocationModel::factory()->create(attributes: ['customer_id' => $attributes['customer_id']])->id,
        ]);
    }

    public function withStatus(AssetStatus $status): self
    {
        return $this->state(state: ['status' => $status]);
    }
}
