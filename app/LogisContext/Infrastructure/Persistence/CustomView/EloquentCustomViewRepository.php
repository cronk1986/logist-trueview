<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Illuminate\Database\Eloquent\Builder;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

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

    public function findOwnedByOrPublicIn(UserId $owner, CustomerId $customer, ?ViewType $type): array
    {
        $query = CustomViewModel::query()
            ->with(relations: CustomViewMapper::relations())
            ->where(column: 'customer_id', operator: $customer->value())
            ->where(column: function (Builder $query) use ($owner): void {
                $query->where(column: 'user_id', operator: $owner->value())
                    ->orWhere(column: 'is_public', operator: true);
            })
            ->orderBy('title')
            ->orderBy('id');

        if ($type !== null) {
            $query->where(column: 'view_type', operator: $type->value);
        }

        $views = $query->get()->map(callback: fn (CustomViewModel $model): CustomView => $this->mapper->toDomain(model: $model));

        return array_values(array: $views->all());
    }
}
