<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Asset;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Exception\Asset\AssetLinkedToAnotherCustomerException;
use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\Asset\SerialNumber;

final class Asset
{
    public function __construct(
        private readonly AssetId $id,
        private readonly Customer $customer,
        private readonly SerialNumber $serialNumber,
        private readonly string $manufacturer,
        private readonly string $model,
        private readonly string $itemName,
        private readonly ?Contract $contract,
        private readonly ?Location $location,
        private readonly AssetStatus $status,
        private readonly CarbonImmutable $createdAt,
    ) {
        $this->ensureTheContractBelongsToTheCustomer();
        $this->ensureTheLocationBelongsToTheCustomer();
    }

    public function id(): AssetId
    {
        return $this->id;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function serialNumber(): SerialNumber
    {
        return $this->serialNumber;
    }

    public function manufacturer(): string
    {
        return $this->manufacturer;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function itemName(): string
    {
        return $this->itemName;
    }

    public function contract(): ?Contract
    {
        return $this->contract;
    }

    public function location(): ?Location
    {
        return $this->location;
    }

    public function status(): AssetStatus
    {
        return $this->status;
    }

    public function createdAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    private function ensureTheContractBelongsToTheCustomer(): void
    {
        if ($this->contract === null) {
            return;
        }

        if (!$this->contract->customer()->isSameAs(other: $this->customer)) {
            throw AssetLinkedToAnotherCustomerException::contract(assetId: $this->id->value(), contractId: $this->contract->id()->value());
        }
    }

    private function ensureTheLocationBelongsToTheCustomer(): void
    {
        if ($this->location === null) {
            return;
        }

        if (!$this->location->customer()->isSameAs(other: $this->customer)) {
            throw AssetLinkedToAnotherCustomerException::location(assetId: $this->id->value(), locationId: $this->location->id()->value());
        }
    }
}
