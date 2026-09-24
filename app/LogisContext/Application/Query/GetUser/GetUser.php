<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\GetUser;

final readonly class GetUser
{
    public function __construct(public int $userId)
    {
    }
}
