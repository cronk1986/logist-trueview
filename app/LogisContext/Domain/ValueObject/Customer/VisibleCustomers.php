<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Customer;

final readonly class VisibleCustomers
{
    private function __construct(
        private ?CustomerId $customer,
        private bool $includesSubAccounts,
    ) {
    }

    public static function none(): self
    {
        return new self(customer: null, includesSubAccounts: false);
    }

    public static function ownCustomer(CustomerId $customer): self
    {
        return new self(customer: $customer, includesSubAccounts: false);
    }

    public static function customerWithSubAccounts(CustomerId $customer): self
    {
        return new self(customer: $customer, includesSubAccounts: true);
    }

    public function isEmpty(): bool
    {
        return $this->customer === null;
    }

    public function customer(): ?CustomerId
    {
        return $this->customer;
    }

    public function includesSubAccounts(): bool
    {
        return $this->includesSubAccounts;
    }
}
