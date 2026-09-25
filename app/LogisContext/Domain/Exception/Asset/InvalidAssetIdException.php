<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Asset;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidAssetIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid asset id.', $value));
    }
}
