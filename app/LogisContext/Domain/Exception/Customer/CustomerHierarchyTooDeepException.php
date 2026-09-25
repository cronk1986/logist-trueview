<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Customer;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class CustomerHierarchyTooDeepException extends InvalidValueException
{
    public static function forParent(int $parentId): self
    {
        return new self(message: sprintf('Customer %d is a sub-account, so it cannot have sub-accounts.', $parentId));
    }
}
