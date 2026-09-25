<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Contract;

use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class ContractTest extends TestCase
{
    public function test_it_belongs_to_its_customer(): void
    {
        $customer = CustomerMother::create(id: 4);

        $contract = ContractMother::create(id: 2, customer: $customer, name: 'OEM Warranty 0002');

        $this->assertSame(2, $contract->id()->value());
        $this->assertSame($customer, $contract->customer());
        $this->assertSame('OEM Warranty 0002', $contract->name());
    }

    public function test_an_active_contract_is_active(): void
    {
        $this->assertTrue(ContractMother::create(status: ContractStatus::Active)->isActive());
        $this->assertFalse(ContractMother::create(status: ContractStatus::Inactive)->isActive());
    }
}
