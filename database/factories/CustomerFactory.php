<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Logis\LogisContext\Domain\Exception\Customer\CustomerHierarchyTooDeepException;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;

/**
 * @extends Factory<CustomerModel>
 */
final class CustomerFactory extends Factory
{
    /** @var class-string<CustomerModel> */
    protected $model = CustomerModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'erp_id' => fake()->unique()->numerify('C-#####'),
            'name' => fake()->company(),
            'parent_customer_id' => null,
        ];
    }

    public function subAccountOf(CustomerModel $parent): self
    {
        if ($parent->parent_customer_id !== null) {
            throw CustomerHierarchyTooDeepException::forParent(parentId: $parent->id);
        }

        return $this->state(state: ['parent_customer_id' => $parent->id]);
    }
}
