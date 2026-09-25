<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Contract;

use Logis\LogisContext\Domain\Exception\Contract\InvalidContractIdException;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;
use PHPUnit\Framework\TestCase;

final class ContractIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, ContractId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidContractIdException::class);

        ContractId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(ContractId::fromInt(value: 3)->equals(other: ContractId::fromInt(value: 3)));
        $this->assertFalse(ContractId::fromInt(value: 3)->equals(other: ContractId::fromInt(value: 4)));
    }
}
