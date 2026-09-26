<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\ConflictException;

final class CustomViewTitleAlreadyUsedException extends ConflictException
{
    public static function withTitle(string $title): self
    {
        return new self(message: sprintf('You already have a view called "%s".', $title));
    }
}
