<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

/**
 * @extends Factory<CustomViewModel>
 */
final class CustomViewFactory extends Factory
{
    /** @var class-string<CustomViewModel> */
    protected $model = CustomViewModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => UserModel::factory(),
            'customer_id' => fn (array $attributes): ?int => UserModel::query()->whereKey(id: $attributes['user_id'])->value(column: 'customer_id'),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'view_type' => ViewType::MyAssets,
            'columns' => ['serial_number', 'manufacturer', 'model', 'location'],
            'filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked']],
            'is_default' => false,
            'is_public' => false,
        ];
    }

    public function ofType(ViewType $type): self
    {
        return $this->state(state: ['view_type' => $type, ...self::configurationOf(type: $type)]);
    }

    /**
     * @return array{columns: list<string>, filters: list<array{column: string, operator: string, value: mixed}>}
     */
    private static function configurationOf(ViewType $type): array
    {
        return match ($type) {
            ViewType::MyAssets => [
                'columns' => ['serial_number', 'manufacturer', 'model', 'location'],
                'filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked']],
            ],
            ViewType::MyTickets => [
                'columns' => ['repair_id', 'subject', 'status'],
                'filters' => [['column' => 'subject', 'operator' => 'contains', 'value' => 'printer', 'user_filter' => 'locked']],
            ],
            ViewType::MyContracts => [
                'columns' => ['name', 'status'],
                'filters' => [['column' => 'status', 'operator' => 'eq', 'value' => 'active', 'user_filter' => 'locked']],
            ],
            ViewType::MyLocations => [
                'columns' => ['title', 'city', 'state'],
                'filters' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 365, 'user_filter' => 'locked']],
            ],
        };
    }

    public function asDefault(): self
    {
        return $this->state(state: ['is_default' => true]);
    }

    public function public(): self
    {
        return $this->state(state: ['is_public' => true]);
    }
}
