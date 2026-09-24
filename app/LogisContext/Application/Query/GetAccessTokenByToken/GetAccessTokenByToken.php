<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\GetAccessTokenByToken;

final readonly class GetAccessTokenByToken
{
    public function __construct(public string $token)
    {
    }
}
