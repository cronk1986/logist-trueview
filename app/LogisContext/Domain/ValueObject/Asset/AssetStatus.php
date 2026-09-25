<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Asset;

enum AssetStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Retired = 'retired';
}
