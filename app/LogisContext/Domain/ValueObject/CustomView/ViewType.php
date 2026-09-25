<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

enum ViewType: string
{
    case MyAssets = 'my_assets';
    case MyTickets = 'my_tickets';
    case MyContracts = 'my_contracts';
    case MyLocations = 'my_locations';
}
