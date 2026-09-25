<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Contract;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidContractIdException extends InvalidValueException
{
    public static function fromValue(int $value): self
    {
        return new self(message: sprintf('"%d" is not a valid contract id.', $value));
    }
}
