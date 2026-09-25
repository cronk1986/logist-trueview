<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Customer;

use Logis\LogisContext\Domain\Exception\Customer\InvalidCustomerIdException;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use PHPUnit\Framework\TestCase;

final class CustomerIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, CustomerId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidCustomerIdException::class);

        CustomerId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(CustomerId::fromInt(value: 3)->equals(other: CustomerId::fromInt(value: 3)));
        $this->assertFalse(CustomerId::fromInt(value: 3)->equals(other: CustomerId::fromInt(value: 4)));
    }
}
