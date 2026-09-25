<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;
use Logis\LogisContext\Domain\ValueObject\Asset\SerialNumber;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractMapper;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationMapper;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class AssetMapper
{
    public function __construct(
        private readonly CustomerMapper $customerMapper,
        private readonly ContractMapper $contractMapper,
        private readonly LocationMapper $locationMapper,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            ...EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations()),
            ...EagerLoad::nested(relation: 'contract', relations: ContractMapper::relations()),
            ...EagerLoad::nested(relation: 'location', relations: LocationMapper::relations()),
        ];
    }

    public function toDomain(AssetModel $model): Asset
    {
        return new Asset(
            id: AssetId::fromInt(value: $model->id),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            serialNumber: SerialNumber::fromString(value: $model->serial_number),
            manufacturer: $model->manufacturer,
            model: $model->model,
            itemName: $model->item_name,
            contract: $this->contract(model: $model),
            location: $this->location(model: $model),
            status: $model->status,
            createdAt: CarbonImmutable::instance(date: $model->created_at)->utc(),
        );
    }

    private function contract(AssetModel $model): ?Contract
    {
        if ($model->contract === null) {
            return null;
        }

        return $this->contractMapper->toDomain(model: $model->contract);
    }

    private function location(AssetModel $model): ?Location
    {
        if ($model->location === null) {
            return null;
        }

        return $this->locationMapper->toDomain(model: $model->location);
    }
}
