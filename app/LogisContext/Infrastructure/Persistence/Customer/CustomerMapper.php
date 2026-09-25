<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\ErpId;

final class CustomerMapper
{
    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['parent'];
    }

    public function toDomain(CustomerModel $model): Customer
    {
        return $this->build(model: $model, parent: $this->parent(model: $model));
    }

    private function parent(CustomerModel $model): ?Customer
    {
        $parent = $model->parent;

        if ($parent === null) {
            return null;
        }

        if ($parent->parent_customer_id !== null) {
            throw CorruptedCustomerHierarchyException::forCustomer(customerId: $model->id, parentId: $parent->id);
        }

        return $this->build(model: $parent, parent: null);
    }

    private function build(CustomerModel $model, ?Customer $parent): Customer
    {
        return new Customer(
            id: CustomerId::fromInt(value: $model->id),
            erpId: ErpId::fromString(value: $model->erp_id),
            name: $model->name,
            parent: $parent,
        );
    }
}
