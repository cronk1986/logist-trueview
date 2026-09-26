<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

final readonly class GetCustomViewByTitle
{
    public function __construct(public int $userId, public string $title)
    {
    }
}
