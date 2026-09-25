<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class LocationMapper
{
    public function __construct(private readonly CustomerMapper $customerMapper)
    {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations());
    }

    public function toDomain(LocationModel $model): Location
    {
        return new Location(
            id: LocationId::fromInt(value: $model->id),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            title: $model->title,
            fullAddress: $model->full_address,
            city: $model->city,
            state: $model->state,
            postalCode: $model->postal_code,
        );
    }
}
