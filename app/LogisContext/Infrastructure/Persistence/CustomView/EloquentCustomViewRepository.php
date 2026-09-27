<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\CustomView;

use Illuminate\Database\UniqueConstraintViolationException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewTitleAlreadyUsedException;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Illuminate\Database\Eloquent\Builder;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
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

    public function findByOwnerAndTitle(UserId $owner, CustomViewTitle $title): ?CustomView
    {
        $model = CustomViewModel::query()
            ->with(relations: CustomViewMapper::relations())
            ->where(column: 'user_id', operator: $owner->value())
            ->where(column: 'title', operator: $title->value())
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }

    public function findDefaultOf(UserId $owner, ViewType $type): ?CustomView
    {
        $model = CustomViewModel::query()
            ->with(relations: CustomViewMapper::relations())
            ->where(column: 'user_id', operator: $owner->value())
            ->where(column: 'view_type', operator: $type->value)
            ->where(column: 'is_default', operator: true)
            ->first();

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

    public function save(CustomView $view): void
    {
        $model = $this->modelOf(view: $view);
        $model->fill(attributes: [
            'user_id' => $view->user()->id()->value(),
            'customer_id' => $view->customer()->id()->value(),
            'title' => $view->title()->value(),
            'description' => $view->description(),
            'view_type' => $view->type(),
            'columns' => $view->columns()->toArray(),
            'filters' => $view->filters()->toArray(),
            'is_default' => $view->isDefault(),
            'is_public' => $view->isPublic(),
        ]);

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw CustomViewTitleAlreadyUsedException::withTitle(title: $view->title()->value());
        }
    }

    public function delete(CustomViewId $id): void
    {
        CustomViewModel::query()->whereKey(id: $id->value())->delete();
    }

    private function modelOf(CustomView $view): CustomViewModel
    {
        $id = $view->id();

        if ($id === null) {
            return new CustomViewModel();
        }

        return CustomViewModel::query()->findOrFail(id: $id->value());
    }
}
