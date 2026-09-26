<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\CustomView;

final readonly class SetDefaultCustomView
{
    public function __construct(public int $userId, public int $viewId)
    {
    }
}
