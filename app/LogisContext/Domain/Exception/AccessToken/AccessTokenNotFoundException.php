<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\AccessToken;

use Logis\SharedContext\Domain\Exception\NotFoundException;

final class AccessTokenNotFoundException extends NotFoundException
{
    public static function create(): self
    {
        return new self(message: 'Access token not found.');
    }
}
