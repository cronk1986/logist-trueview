<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

final class DemoDataSeeder extends Seeder
{
    public const CONTRACTS_PER_CUSTOMER = 3;
    public const LOCATIONS_PER_CUSTOMER = 5;
    public const ASSETS_PER_CUSTOMER = 30;
    public const TICKETS_PER_CUSTOMER = 20;

    public function run(): void
    {
        $parent = CustomerModel::factory()->create(attributes: ['erp_id' => 'C-1000', 'name' => 'Logis Demo']);
        $subAccounts = [
            CustomerModel::factory()->subAccountOf(parent: $parent)->create(attributes: ['erp_id' => 'C-1001', 'name' => 'Logis Demo North']),
            CustomerModel::factory()->subAccountOf(parent: $parent)->create(attributes: ['erp_id' => 'C-1002', 'name' => 'Logis Demo South']),
        ];
        $unrelated = CustomerModel::factory()->create(attributes: ['erp_id' => 'C-2000', 'name' => 'Other Company']);

        $demoUser = UserModel::factory()->withSubAccountAccess()->create(attributes: [
            'customer_id' => $parent->id,
            'name' => 'Demo',
            'email' => 'demo@logis.test',
            'password' => 'password',
        ]);
        $limitedUser = UserModel::factory()->create(attributes: [
            'customer_id' => $parent->id,
            'name' => 'Limited',
            'email' => 'limited@logis.test',
            'password' => 'password',
        ]);

        foreach ([$parent, ...$subAccounts, $unrelated] as $customer) {
            $this->seedCustomer(customer: $customer, creator: $this->creatorFor(customer: $customer, demoUser: $demoUser));
        }

        $this->seedViews(owner: $demoUser);
        $this->seedSharedViews(colleague: $limitedUser, stranger: UserModel::factory()->create(attributes: ['customer_id' => $unrelated->id]));
    }

    private function creatorFor(CustomerModel $customer, UserModel $demoUser): UserModel
    {
        if ($customer->id === $demoUser->customer_id) {
            return $demoUser;
        }

        return UserModel::factory()->create(attributes: ['customer_id' => $customer->id]);
    }

    private function seedCustomer(CustomerModel $customer, UserModel $creator): void
    {
        $contracts = ContractModel::factory()->count(count: self::CONTRACTS_PER_CUSTOMER)->create(attributes: ['customer_id' => $customer->id]);
        $locations = LocationModel::factory()->count(count: self::LOCATIONS_PER_CUSTOMER)->create(attributes: ['customer_id' => $customer->id]);

        $assets = AssetModel::factory()
            ->count(count: self::ASSETS_PER_CUSTOMER)
            ->sequence(
                ['status' => AssetStatus::Active],
                ['status' => AssetStatus::Active],
                ['status' => AssetStatus::Inactive],
                ['status' => AssetStatus::Retired],
            )
            ->create(attributes: ['customer_id' => $customer->id])
            ->each(callback: function (AssetModel $asset) use ($contracts, $locations): void {
                $asset->update(attributes: [
                    'contract_id' => $contracts->random()->id,
                    'location_id' => $locations->random()->id,
                ]);
            });

        $statusIds = TicketStatusModel::query()->pluck(column: 'id');

        TicketModel::factory()
            ->count(count: self::TICKETS_PER_CUSTOMER)
            ->state(state: fn (): array => [
                'asset_id' => $assets->random()->id,
                'status_id' => $statusIds->random(),
            ])
            ->create(attributes: ['customer_id' => $customer->id, 'created_by' => $creator->id]);
    }

    private function seedViews(UserModel $owner): void
    {
        CustomViewModel::factory()->ofType(type: ViewType::MyAssets)->asDefault()->create(attributes: [
            'user_id' => $owner->id,
            'customer_id' => $owner->customer_id,
            'title' => 'Zebra scanners',
            'description' => 'Every active Zebra device',
            'columns' => ['serial_number', 'manufacturer', 'model', 'location'],
            'filters' => [
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra'],
                ['column' => 'status', 'operator' => 'eq', 'value' => 'active'],
            ],
        ]);

        CustomViewModel::factory()->ofType(type: ViewType::MyTickets)->public()->create(attributes: [
            'user_id' => $owner->id,
            'customer_id' => $owner->customer_id,
            'title' => 'Open tickets',
            'description' => 'Tickets that are still open, shared with the whole customer',
            'columns' => ['repair_id', 'subject', 'status'],
            'filters' => [['column' => 'status', 'operator' => 'eq', 'value' => $this->ticketStatusId(name: 'Open')]],
        ]);
    }

    private function seedSharedViews(UserModel $colleague, UserModel $stranger): void
    {
        CustomViewModel::factory()->ofType(type: ViewType::MyAssets)->public()->create(attributes: [
            'user_id' => $colleague->id,
            'customer_id' => $colleague->customer_id,
            'title' => 'Retired devices',
            'description' => 'Assets retired from service, shared with the whole customer',
            'columns' => ['serial_number', 'model', 'status', 'updated_at'],
            'filters' => [['column' => 'status', 'operator' => 'eq', 'value' => 'retired']],
        ]);

        CustomViewModel::factory()->ofType(type: ViewType::MyContracts)->public()->create(attributes: [
            'user_id' => $stranger->id,
            'customer_id' => $stranger->customer_id,
            'title' => 'Active contracts',
            'description' => 'Visible only inside Other Company',
        ]);
    }

    private function ticketStatusId(string $name): int
    {
        return (int) TicketStatusModel::query()->where(column: 'name', operator: $name)->valueOrFail(column: 'id');
    }
}
