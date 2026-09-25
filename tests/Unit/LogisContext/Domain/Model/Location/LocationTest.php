<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Location;

use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class LocationTest extends TestCase
{
    public function test_it_belongs_to_its_customer_and_keeps_its_address(): void
    {
        $customer = CustomerMother::create(id: 4);

        $location = LocationMother::create(id: 2, customer: $customer, title: 'Madrid Warehouse');

        $this->assertSame(2, $location->id()->value());
        $this->assertSame($customer, $location->customer());
        $this->assertSame('Madrid Warehouse', $location->title());
        $this->assertSame('Calle Mayor 1', $location->fullAddress());
        $this->assertSame('Madrid', $location->city());
        $this->assertSame('Madrid', $location->state());
        $this->assertSame('28013', $location->postalCode());
    }
}
