<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
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

    public function test_a_customer_with_users_cannot_be_deleted(): void
    {
        $customer = CustomerModel::factory()->create();
        UserModel::factory()->create(attributes: ['customer_id' => $customer->id]);

        $this->expectException(QueryException::class);

        $customer->delete();
    }

    public function test_the_name_of_a_ticket_status_is_unique(): void
    {
        TicketStatusModel::factory()->create(attributes: ['name' => 'Open']);

        $this->expectException(QueryException::class);

        TicketStatusModel::factory()->create(attributes: ['name' => 'Open']);
    }

    public function test_a_customer_with_contracts_cannot_be_deleted(): void
    {
        $contract = ContractModel::factory()->create();

        $this->expectException(QueryException::class);

        CustomerModel::query()->whereKey(id: $contract->customer_id)->delete();
    }
}
