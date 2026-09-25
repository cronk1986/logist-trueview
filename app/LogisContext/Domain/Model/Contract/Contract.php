<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Contract;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;

final class Contract
{
    public function __construct(
        private readonly ContractId $id,
        private readonly Customer $customer,
        private readonly string $name,
        private readonly ContractStatus $status,
    ) {
    }

    public function id(): ContractId
    {
        return $this->id;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): ContractStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === ContractStatus::Active;
    }
}
