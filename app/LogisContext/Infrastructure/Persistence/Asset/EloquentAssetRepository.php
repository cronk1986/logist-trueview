<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Asset;

use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Asset\AssetRepository;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;

final class EloquentAssetRepository implements AssetRepository
{
    public function __construct(private readonly AssetMapper $mapper)
    {
    }

    public function findById(AssetId $id): ?Asset
    {
        $model = AssetModel::query()->with(relations: AssetMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
