<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Asset;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\Asset\SerialNumber;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class AssetMother
{
    public static function create(
        int $id = 1,
        ?Customer $customer = null,
        string $serialNumber = 'SN-00000001',
        ?Contract $contract = null,
        ?Location $location = null,
        AssetStatus $status = AssetStatus::Active,
    ): Asset {
        return new Asset(
            id: AssetId::fromInt(value: $id),
            customer: $customer ?? CustomerMother::create(),
            serialNumber: SerialNumber::fromString(value: $serialNumber),
            manufacturer: 'Zebra',
            model: 'TC52',
            itemName: 'Zebra TC52',
            contract: $contract,
            location: $location,
            status: $status,
            createdAt: CarbonImmutable::parse(time: '2026-01-15 09:30:00', timezone: 'UTC'),
        );
    }
}
