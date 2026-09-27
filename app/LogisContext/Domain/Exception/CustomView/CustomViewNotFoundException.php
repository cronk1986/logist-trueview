<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\NotFoundException;

final class CustomViewNotFoundException extends NotFoundException
{
    public static function withId(int $id): self
    {
        return new self(message: sprintf('View %d not found.', $id));
    }
}
