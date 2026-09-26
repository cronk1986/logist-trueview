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

    public static function fromValue(string $value): self
    {
        $type = self::tryFrom(value: $value);

        if ($type === null) {
            throw InvalidViewTypeException::unknown(type: $value);
        }

        return $type;
    }
}
