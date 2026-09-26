<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use LogicException;

final class CustomViewNotSavedException extends LogicException
{
    public static function create(): self
    {
        return new self(message: 'The view has no id until it is saved.');
    }
}
