<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use Logis\LogisContext\Domain\Exception\Customer\CustomerHierarchyTooDeepException;
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
        return new Customer(
            id: CustomerId::fromInt(value: $model->id),
            erpId: ErpId::fromString(value: $model->erp_id),
            name: $model->name,
            parent: $this->parent(model: $model),
        );
    }

    private function parent(CustomerModel $model): ?Customer
    {
        $parent = $model->parent;

        if ($parent === null) {
            return null;
        }

        if ($parent->parent_customer_id !== null) {
            throw CustomerHierarchyTooDeepException::forParent(parentId: $parent->id);
        }

        return new Customer(
            id: CustomerId::fromInt(value: $parent->id),
            erpId: ErpId::fromString(value: $parent->erp_id),
            name: $parent->name,
            parent: null,
        );
    }
}
