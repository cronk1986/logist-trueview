<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Search;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidSearchTermException extends InvalidValueException
{
    public static function empty(): self
    {
        return new self(message: 'The search term must not be empty.');
    }
}
