<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMERS = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(class: DatabaseSeeder::class);
    }

    public function test_it_seeds_a_parent_customer_with_two_sub_accounts_and_an_unrelated_customer(): void
    {
        $parent = CustomerModel::query()->where(column: 'erp_id', operator: 'C-1000')->firstOrFail();

        $this->assertSame(self::CUSTOMERS, CustomerModel::query()->count());
        $this->assertSame(['C-1001', 'C-1002'], $parent->subAccounts()->orderBy('erp_id')->pluck(column: 'erp_id')->all());
        $this->assertNull(CustomerModel::query()->where(column: 'erp_id', operator: 'C-2000')->value(column: 'parent_customer_id'));
    }

    public function test_the_demo_user_belongs_to_the_parent_customer_and_owns_two_views(): void
    {
        $demoUser = UserModel::query()->where(column: 'email', operator: 'demo@logis.test')->firstOrFail();

        $this->assertSame('C-1000', $demoUser->customer?->erp_id);
        $this->assertSame(1, CustomViewModel::query()->where(column: 'user_id', operator: $demoUser->id)->where(column: 'is_default', operator: true)->count());
        $this->assertSame(1, CustomViewModel::query()->where(column: 'user_id', operator: $demoUser->id)->where(column: 'is_public', operator: true)->count());
    }

    public function test_only_the_demo_user_can_view_the_sub_accounts_of_the_parent_customer(): void
    {
        $demoUser = UserModel::query()->where(column: 'email', operator: 'demo@logis.test')->firstOrFail();
        $limitedUser = UserModel::query()->where(column: 'email', operator: 'limited@logis.test')->firstOrFail();

        $this->assertTrue($demoUser->can_view_sub_accounts);
        $this->assertFalse($limitedUser->can_view_sub_accounts);
        $this->assertSame($demoUser->customer_id, $limitedUser->customer_id);
    }

    public function test_every_customer_gets_its_catalog(): void
    {
        $this->assertSame(self::CUSTOMERS * DemoDataSeeder::CONTRACTS_PER_CUSTOMER, ContractModel::query()->count());
        $this->assertSame(self::CUSTOMERS * DemoDataSeeder::LOCATIONS_PER_CUSTOMER, LocationModel::query()->count());
        $this->assertSame(self::CUSTOMERS * DemoDataSeeder::ASSETS_PER_CUSTOMER, AssetModel::query()->count());
        $this->assertSame(self::CUSTOMERS * DemoDataSeeder::TICKETS_PER_CUSTOMER, TicketModel::query()->count());
    }

    public function test_assets_and_tickets_only_point_to_records_of_their_own_customer(): void
    {
        $foreignAssets = AssetModel::query()
            ->join('contracts', 'contracts.id', '=', 'assets.contract_id')
            ->whereColumn('contracts.customer_id', '!=', 'assets.customer_id')
            ->count();
        $foreignTickets = TicketModel::query()
            ->join('assets', 'assets.id', '=', 'tickets.asset_id')
            ->whereColumn('assets.customer_id', '!=', 'tickets.customer_id')
            ->count();

        $this->assertSame(0, $foreignAssets);
        $this->assertSame(0, $foreignTickets);
    }

    public function test_the_demo_user_can_log_in(): void
    {
        $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'demo@logis.test', 'password' => 'password'])->assertOk();
    }
}
