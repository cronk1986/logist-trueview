<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Factories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\Exception\Customer\CustomerHierarchyTooDeepException;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\TestCase;

final class CustomerFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sub_account_cannot_be_the_parent_of_another_sub_account(): void
    {
        $subAccount = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();

        $this->expectException(CustomerHierarchyTooDeepException::class);

        CustomerModel::factory()->subAccountOf(parent: $subAccount);
    }
}
