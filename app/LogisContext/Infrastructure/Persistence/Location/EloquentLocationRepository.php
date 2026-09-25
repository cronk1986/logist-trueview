<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Location;

use Logis\LogisContext\Domain\Model\Location\Location;
use Logis\LogisContext\Domain\Model\Location\LocationRepository;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;

final class EloquentLocationRepository implements LocationRepository
{
    public function __construct(private readonly LocationMapper $mapper)
    {
    }

    public function findById(LocationId $id): ?Location
    {
        $model = LocationModel::query()->with(relations: LocationMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
