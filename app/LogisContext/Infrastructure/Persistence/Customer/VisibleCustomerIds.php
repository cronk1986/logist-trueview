<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use Illuminate\Database\Eloquent\Builder;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;

final class VisibleCustomerIds
{
    /**
     * @return Builder<CustomerModel>
     */
    public static function of(VisibleCustomers $customers): Builder
    {
        $ids = CustomerModel::query()->select('id');
        $customer = $customers->customer();

        if ($customer === null) {
            return $ids->whereKey(id: []);
        }

        if (!$customers->includesSubAccounts()) {
            return $ids->whereKey(id: $customer->value());
        }

        return $ids->where(column: function (Builder $query) use ($customer): void {
            $query->whereKey(id: $customer->value())
                ->orWhere(column: 'parent_customer_id', operator: $customer->value());
        });
    }
}
