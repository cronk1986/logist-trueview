<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class InMemoryCustomViewRepository implements CustomViewRepository
{
    /** @var array<int, CustomView> */
    private array $views = [];

    /** @var list<int> */
    private array $lockedOwners = [];

    public function add(CustomView $view): void
    {
        $this->views[$view->savedId()->value()] = $view;
    }

    public function findById(CustomViewId $id): ?CustomView
    {
        return $this->views[$id->value()] ?? null;
    }

    public function findByOwnerAndTitle(UserId $owner, CustomViewTitle $title): ?CustomView
    {
        foreach ($this->views as $view) {
            if ($view->user()->id()->equals(other: $owner) && $view->title()->equals(other: $title)) {
                return $view;
            }
        }

        return null;
    }

    public function findDefaultOf(UserId $owner, ViewType $type): ?CustomView
    {
        foreach ($this->views as $view) {
            if ($view->user()->id()->equals(other: $owner) && $view->type() === $type && $view->isDefault()) {
                return $view;
            }
        }

        return null;
    }

    public function findOwnedByOrPublicIn(UserId $owner, CustomerId $customer, ?ViewType $type): array
    {
        $views = array_filter(
            array: $this->views,
            callback: fn (CustomView $view): bool => $view->customer()->id()->equals(other: $customer)
                && ($view->user()->id()->equals(other: $owner) || $view->isPublic())
                && ($type === null || $view->type() === $type),
        );

        usort(array: $views, callback: fn (CustomView $a, CustomView $b): int => [$a->title()->value(), $a->savedId()->value()] <=> [$b->title()->value(), $b->savedId()->value()]);

        return $views;
    }

    public function lockViewsOf(UserId $owner): void
    {
        $this->lockedOwners[] = $owner->value();
    }

    /**
     * @return list<int>
     */
    public function lockedOwners(): array
    {
        return $this->lockedOwners;
    }

    public function save(CustomView $view): void
    {
        $this->add(view: $this->withId(view: $view));
    }

    public function delete(CustomViewId $id): void
    {
        unset($this->views[$id->value()]);
    }

    private function withId(CustomView $view): CustomView
    {
        if ($view->id() !== null) {
            return $view;
        }

        return new CustomView(
            id: CustomViewId::fromInt(value: max([0, ...array_keys(array: $this->views)]) + 1),
            user: $view->user(),
            customer: $view->customer(),
            title: $view->title(),
            description: $view->description(),
            type: $view->type(),
            columns: $view->columns(),
            filters: $view->filters(),
            isDefault: $view->isDefault(),
            isPublic: $view->isPublic(),
        );
    }
}
