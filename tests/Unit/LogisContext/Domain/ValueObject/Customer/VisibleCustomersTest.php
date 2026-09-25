<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Customer;

use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use PHPUnit\Framework\TestCase;

final class VisibleCustomersTest extends TestCase
{
    public function test_none_is_empty(): void
    {
        $customers = VisibleCustomers::none();

        $this->assertTrue($customers->isEmpty());
        $this->assertSame([], $customers->values());
    }

    public function test_it_sorts_the_ids(): void
    {
        $customers = VisibleCustomers::fromIds(ids: [CustomerId::fromInt(value: 9), CustomerId::fromInt(value: 2), CustomerId::fromInt(value: 5)]);

        $this->assertFalse($customers->isEmpty());
        $this->assertSame([2, 5, 9], $customers->values());
    }

    public function test_it_removes_duplicates(): void
    {
        $customers = VisibleCustomers::fromIds(ids: [CustomerId::fromInt(value: 3), CustomerId::fromInt(value: 3), CustomerId::fromInt(value: 1)]);

        $this->assertSame([1, 3], $customers->values());
    }
}
