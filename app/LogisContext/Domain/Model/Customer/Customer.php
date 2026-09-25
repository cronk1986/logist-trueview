<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Exception\Customer\CustomerCannotBeItsOwnParentException;
use Logis\LogisContext\Domain\Exception\Customer\CustomerHierarchyTooDeepException;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\ErpId;

final class Customer
{
    public function __construct(
        private readonly CustomerId $id,
        private readonly ErpId $erpId,
        private readonly string $name,
        private readonly ?Customer $parent,
    ) {
        $this->ensureTheParentIsATopLevelCustomer();
    }

    public function id(): CustomerId
    {
        return $this->id;
    }

    public function erpId(): ErpId
    {
        return $this->erpId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function parent(): ?Customer
    {
        return $this->parent;
    }

    public function isSameAs(Customer $other): bool
    {
        return $this->id->equals(other: $other->id());
    }

    public function isSubAccount(): bool
    {
        return $this->parent !== null;
    }

    private function ensureTheParentIsATopLevelCustomer(): void
    {
        if ($this->parent === null) {
            return;
        }

        if ($this->parent->id()->equals(other: $this->id)) {
            throw CustomerCannotBeItsOwnParentException::withId(id: $this->id->value());
        }

        if ($this->parent->isSubAccount()) {
            throw CustomerHierarchyTooDeepException::forParent(parentId: $this->parent->id()->value());
        }
    }
}
