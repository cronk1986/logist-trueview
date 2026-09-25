<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Customer;

use RuntimeException;

final class CorruptedCustomerHierarchyException extends RuntimeException
{
    public static function forCustomer(int $customerId, int $parentId): self
    {
        return new self(message: sprintf('Customer %d has parent %d, which is not a top-level customer.', $customerId, $parentId));
    }
}
