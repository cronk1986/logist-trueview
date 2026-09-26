<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\CustomViewLinkedToAnotherCustomerException;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class CustomView
{
    private ViewDefinition $definition;

    public function __construct(
        private readonly ?CustomViewId $id,
        private readonly User $user,
        private readonly Customer $customer,
        private CustomViewTitle $title,
        private ?string $description,
        ViewType $type,
        ViewColumns $columns,
        ViewFilters $filters,
        private bool $isDefault,
        private bool $isPublic,
    ) {
        $this->ensureTheViewBelongsToTheCustomerOfItsOwner();
        $this->definition = ViewDefinition::from(type: $type, columns: $columns, filters: $filters);
    }

    public function id(): ?CustomViewId
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function title(): CustomViewTitle
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function type(): ViewType
    {
        return $this->definition->type();
    }

    public function definition(): ViewDefinition
    {
        return $this->definition;
    }

    public function columns(): ViewColumns
    {
        return $this->definition->columns();
    }

    public function filters(): ViewFilters
    {
        return $this->definition->filters();
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    private function ensureTheViewBelongsToTheCustomerOfItsOwner(): void
    {
        $ownerCustomer = $this->user->customer();

        if ($ownerCustomer === null || !$ownerCustomer->isSameAs(other: $this->customer)) {
            throw CustomViewLinkedToAnotherCustomerException::owner(userId: $this->user->id()->value());
        }
    }
}
