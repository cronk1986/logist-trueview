<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Customer\CustomerRepository;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;

final class EloquentCustomerRepository implements CustomerRepository
{
    public function __construct(private readonly CustomerMapper $mapper)
    {
    }

    public function findById(CustomerId $id): ?Customer
    {
        $model = CustomerModel::query()->with(relations: CustomerMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }

    /**
     * @return list<Customer>
     */
    public function subAccountsOf(CustomerId $id): array
    {
        $models = CustomerModel::query()
            ->with(relations: CustomerMapper::relations())
            ->where(column: 'parent_customer_id', operator: $id->value())
            ->orderBy('id')
            ->get();

        return array_values(array: $models->map(callback: fn (CustomerModel $model): Customer => $this->mapper->toDomain(model: $model))->all());
    }
}
