<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Location;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;

final class LocationMother
{
    public static function create(int $id = 1, ?Customer $customer = null, string $title = 'Madrid Warehouse'): Location
    {
        return new Location(
            id: LocationId::fromInt(value: $id),
            customer: $customer ?? CustomerMother::create(),
            title: $title,
            fullAddress: 'Calle Mayor 1',
            city: 'Madrid',
            state: 'Madrid',
            postalCode: '28013',
        );
    }
}
