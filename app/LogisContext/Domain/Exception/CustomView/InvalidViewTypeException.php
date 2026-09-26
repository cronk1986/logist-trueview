<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewTypeException extends InvalidValueException
{
    public static function unknown(string $type): self
    {
        return new self(message: sprintf('"%s" is not a view type.', $type));
    }
}
