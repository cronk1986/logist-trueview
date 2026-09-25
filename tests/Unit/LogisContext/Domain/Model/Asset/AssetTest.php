<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Asset;

use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Contract\ContractMother;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\Location\LocationMother;

final class AssetTest extends TestCase
{
    public function test_it_holds_its_customer_contract_and_location(): void
    {
        $customer = CustomerMother::create(id: 4);
        $contract = ContractMother::create(customer: $customer);
        $location = LocationMother::create(customer: $customer);

        $asset = AssetMother::create(id: 2, customer: $customer, serialNumber: 'SN-1', contract: $contract, location: $location);

        $this->assertSame(2, $asset->id()->value());
        $this->assertSame($customer, $asset->customer());
        $this->assertSame('SN-1', $asset->serialNumber()->value());
        $this->assertSame('Zebra', $asset->manufacturer());
        $this->assertSame('TC52', $asset->model());
        $this->assertSame('Zebra TC52', $asset->itemName());
        $this->assertSame($contract, $asset->contract());
        $this->assertSame($location, $asset->location());
        $this->assertSame('2026-01-15 09:30:00', $asset->createdAt()->format(format: 'Y-m-d H:i:s'));
    }

    public function test_the_contract_and_location_are_optional(): void
    {
        $asset = AssetMother::create();

        $this->assertNull($asset->contract());
        $this->assertNull($asset->location());
    }

    public function test_only_an_active_asset_is_active(): void
    {
        $this->assertTrue(AssetMother::create(status: AssetStatus::Active)->isActive());
        $this->assertFalse(AssetMother::create(status: AssetStatus::Inactive)->isActive());
        $this->assertFalse(AssetMother::create(status: AssetStatus::Retired)->isActive());
    }
}
