<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class InMemoryCustomViewRepository implements CustomViewRepository
{
    /** @var array<int, CustomView> */
    private array $views = [];

    public function add(CustomView $view): void
    {
        $this->views[$view->savedId()->value()] = $view;
    }

    public function findById(CustomViewId $id): ?CustomView
    {
        return $this->views[$id->value()] ?? null;
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
}
