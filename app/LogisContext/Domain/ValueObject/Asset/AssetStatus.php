<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Asset;

enum AssetStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Retired = 'retired';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
