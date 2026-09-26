<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class CustomViewLinkedToAnotherCustomerException extends InvalidValueException
{
    public static function owner(int $userId): self
    {
        return new self(message: sprintf('A view must belong to the customer of its owner, user %d.', $userId));
    }
}
