<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewTypeException;

enum ViewType: string
{
    case MyAssets = 'my_assets';
    case MyTickets = 'my_tickets';
    case MyContracts = 'my_contracts';
    case MyLocations = 'my_locations';

    public static function fromName(string $name): self
    {
        $type = self::tryFrom(value: $name);

        if ($type === null) {
            throw InvalidViewTypeException::unknown(type: $name);
        }

        return $type;
    }
}
