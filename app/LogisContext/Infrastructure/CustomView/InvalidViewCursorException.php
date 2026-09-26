<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewCursorException extends InvalidValueException
{
    public static function malformed(): self
    {
        return new self(message: 'The cursor is not valid. Use the next_cursor of the previous page.');
    }

    public static function forAnotherSort(): self
    {
        return new self(message: 'The cursor belongs to another sort order. Start again from the first page.');
    }
}
