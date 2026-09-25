<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;

final class VisibleCustomersResolver
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    public function for(User $user): VisibleCustomers
    {
        $customer = $user->customer();

        if ($customer === null) {
            return VisibleCustomers::none();
        }

        if (!$user->canViewSubAccounts()) {
            return VisibleCustomers::fromIds(ids: [$customer->id()]);
        }

        return VisibleCustomers::fromIds(ids: [$customer->id(), ...$this->subAccountIdsOf(customer: $customer)]);
    }

    /**
     * @return list<CustomerId>
     */
    private function subAccountIdsOf(Customer $customer): array
    {
        return array_map(
            callback: fn (Customer $subAccount): CustomerId => $subAccount->id(),
            array: $this->customers->subAccountsOf(id: $customer->id()),
        );
    }
}
