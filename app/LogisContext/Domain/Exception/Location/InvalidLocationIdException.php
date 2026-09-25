<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Location;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidLocationIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid location id.', $value));
    }
}
