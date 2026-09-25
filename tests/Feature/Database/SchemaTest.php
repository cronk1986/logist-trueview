<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
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

    public function test_a_customer_with_locations_cannot_be_deleted(): void
    {
        $location = LocationModel::factory()->create();

        $this->expectException(QueryException::class);

        CustomerModel::query()->whereKey(id: $location->customer_id)->delete();
    }

    public function test_the_serial_number_of_an_asset_is_unique_even_after_a_soft_delete(): void
    {
        AssetModel::factory()->create(attributes: ['serial_number' => 'SN-1'])->delete();

        $this->expectException(QueryException::class);

        AssetModel::factory()->create(attributes: ['serial_number' => 'SN-1']);
    }

    public function test_deleting_a_contract_or_a_location_unlinks_its_assets(): void
    {
        $asset = AssetModel::factory()->withContract()->withLocation()->create();

        ContractModel::query()->whereKey(id: $asset->contract_id)->delete();
        LocationModel::query()->whereKey(id: $asset->location_id)->delete();

        $asset->refresh();
        $this->assertNull($asset->contract_id);
        $this->assertNull($asset->location_id);
    }

    public function test_the_repair_id_of_a_ticket_is_unique(): void
    {
        TicketModel::factory()->create(attributes: ['repair_id' => 'RMA-1']);

        $this->expectException(QueryException::class);

        TicketModel::factory()->create(attributes: ['repair_id' => 'RMA-1']);
    }

    public function test_the_incident_number_of_a_ticket_is_unique(): void
    {
        TicketModel::factory()->create(attributes: ['incident_number' => 'INC-1']);

        $this->expectException(QueryException::class);

        TicketModel::factory()->create(attributes: ['incident_number' => 'INC-1']);
    }

    public function test_deleting_an_asset_unlinks_its_tickets(): void
    {
        $ticket = TicketModel::factory()->withAsset()->create();

        AssetModel::query()->whereKey(id: $ticket->asset_id)->forceDelete();

        $this->assertNull($ticket->refresh()->asset_id);
    }

    public function test_a_ticket_status_in_use_cannot_be_deleted(): void
    {
        $ticket = TicketModel::factory()->create();

        $this->expectException(QueryException::class);

        TicketStatusModel::query()->whereKey(id: $ticket->status_id)->delete();
    }

    public function test_a_user_cannot_repeat_a_view_title(): void
    {
        $view = CustomViewModel::factory()->create(attributes: ['title' => 'Zebra scanners']);

        $this->expectException(QueryException::class);

        CustomViewModel::factory()->create(attributes: ['user_id' => $view->user_id, 'title' => 'Zebra scanners']);
    }

    public function test_different_users_can_use_the_same_view_title(): void
    {
        CustomViewModel::factory()->create(attributes: ['title' => 'Zebra scanners']);
        CustomViewModel::factory()->create(attributes: ['title' => 'Zebra scanners']);

        $this->assertSame(2, CustomViewModel::query()->where(column: 'title', operator: 'Zebra scanners')->count());
    }

    public function test_deleting_a_user_deletes_their_views(): void
    {
        $view = CustomViewModel::factory()->create();

        UserModel::query()->whereKey(id: $view->user_id)->delete();

        $this->assertFalse(CustomViewModel::query()->whereKey(id: $view->id)->exists());
    }
}
