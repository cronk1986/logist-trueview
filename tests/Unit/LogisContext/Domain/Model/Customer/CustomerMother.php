<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\ErpId;

final class CustomerMother
{
    public static function create(int $id = 1, string $erpId = 'C-1000', string $name = 'Logis Demo', ?Customer $parent = null): Customer
    {
        return new Customer(id: CustomerId::fromInt(value: $id), erpId: ErpId::fromString(value: $erpId), name: $name, parent: $parent);
    }
}
