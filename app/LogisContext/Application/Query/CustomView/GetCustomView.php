<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

final readonly class GetCustomView
{
    public function __construct(public int $userId, public int $viewId)
    {
    }
}
