<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class CustomViewTypeCannotChangeException extends InvalidValueException
{
    public static function from(string $currentType): self
    {
        return new self(message: sprintf('The type of a view cannot change. This view stays %s.', $currentType));
    }
}
