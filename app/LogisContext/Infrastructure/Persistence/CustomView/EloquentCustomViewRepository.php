<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;

final class EloquentCustomViewRepository implements CustomViewRepository
{
    public function __construct(private readonly CustomViewMapper $mapper)
    {
    }

    public function findById(CustomViewId $id): ?CustomView
    {
        $model = CustomViewModel::query()->with(relations: CustomViewMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
