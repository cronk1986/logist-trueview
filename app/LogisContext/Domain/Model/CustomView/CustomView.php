<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class CustomView
{
    public function __construct(
        private readonly CustomViewId $id,
        private readonly User $user,
        private readonly Customer $customer,
        private readonly CustomViewTitle $title,
        private readonly ?string $description,
        private readonly ViewType $type,
        private readonly ViewColumns $columns,
        private readonly ViewFilters $filters,
        private readonly bool $isDefault,
        private readonly bool $isPublic,
    ) {
    }

    public function id(): CustomViewId
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
        return $this->type;
    }

    public function columns(): ViewColumns
    {
        return $this->columns;
    }

    public function filters(): ViewFilters
    {
        return $this->filters;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }
}
