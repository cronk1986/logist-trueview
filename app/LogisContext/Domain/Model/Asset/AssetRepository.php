<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Asset;

use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;

interface AssetRepository
{
    public function findById(AssetId $id): ?Asset;
}
