<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Location;

use Logis\LogisContext\Domain\ValueObject\Location\LocationId;

interface LocationRepository
{
    public function findById(LocationId $id): ?Location;
}
