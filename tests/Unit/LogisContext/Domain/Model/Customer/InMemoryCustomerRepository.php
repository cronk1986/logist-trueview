<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Customer\CustomerRepository;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;

final class InMemoryCustomerRepository implements CustomerRepository
{
    /** @var array<int, Customer> */
    private array $customers = [];

    public function add(Customer $customer): void
    {
        $this->customers[$customer->id()->value()] = $customer;
    }

    public function findById(CustomerId $id): ?Customer
    {
        return $this->customers[$id->value()] ?? null;
    }

    /**
     * @return list<Customer>
     */
    public function subAccountsOf(CustomerId $id): array
    {
        return array_values(array: array_filter(
            array: $this->customers,
            callback: fn (Customer $customer): bool => $customer->parent() !== null && $customer->parent()->id()->equals(other: $id),
        ));
    }
}
