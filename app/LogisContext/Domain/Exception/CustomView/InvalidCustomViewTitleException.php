<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidCustomViewTitleException extends InvalidValueException
{
    public static function create(): self
    {
        return new self(message: 'The custom view title must have between 1 and 255 characters.');
    }
}
