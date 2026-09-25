<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Contract;

use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class ContractMother
{
    public static function create(
        int $id = 1,
        ?Customer $customer = null,
        string $name = 'TrueSupport 0001',
        ContractStatus $status = ContractStatus::Active,
    ): Contract {
        return new Contract(id: ContractId::fromInt(value: $id), customer: $customer ?? CustomerMother::create(), name: $name, status: $status);
    }
}
