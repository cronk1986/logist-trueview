<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Tests\TestCase;

final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_erp_id_of_a_customer_is_unique(): void
    {
        CustomerModel::factory()->create(attributes: ['erp_id' => 'C-1000']);

        $this->expectException(QueryException::class);

        CustomerModel::factory()->create(attributes: ['erp_id' => 'C-1000']);
    }

    public function test_a_customer_with_sub_accounts_cannot_be_deleted(): void
    {
        $parent = CustomerModel::factory()->create();
        CustomerModel::factory()->subAccountOf(parent: $parent)->create();

        $this->expectException(QueryException::class);

        $parent->delete();
    }
}
