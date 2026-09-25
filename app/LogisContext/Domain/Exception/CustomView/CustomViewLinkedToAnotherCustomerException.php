<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class CustomViewLinkedToAnotherCustomerException extends InvalidValueException
{
    public static function owner(int $viewId, int $userId): self
    {
        return new self(message: sprintf('View %d must belong to the customer of its owner, user %d.', $viewId, $userId));
    }
}
