<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private readonly string $name,
        private readonly Email $email,
        private readonly string $passwordHash,
        private readonly ?Customer $customer,
        private readonly bool $canViewSubAccounts,
    ) {
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function customer(): ?Customer
    {
        return $this->customer;
    }

    public function canViewSubAccounts(): bool
    {
        return $this->canViewSubAccounts;
    }

    public function visibleCustomers(): VisibleCustomers
    {
        if ($this->customer === null) {
            return VisibleCustomers::none();
        }

        if (!$this->canViewSubAccounts) {
            return VisibleCustomers::ownCustomer(customer: $this->customer->id());
        }

        return VisibleCustomers::customerWithSubAccounts(customer: $this->customer->id());
    }
}
