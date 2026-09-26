<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
