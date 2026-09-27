<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

enum UserFilterMode: string
{
    case Restricted = 'restricted';
    case Open = 'open';
    case Locked = 'locked';

    public function allowsUserFilter(): bool
    {
        return $this !== self::Locked;
    }

    public function replacesSavedFilters(): bool
    {
        return $this === self::Open;
    }

    public function requiresDiscreteValues(): bool
    {
        return $this === self::Restricted;
    }
}
