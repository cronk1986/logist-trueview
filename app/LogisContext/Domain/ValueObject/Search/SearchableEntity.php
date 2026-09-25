<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Search;

enum SearchableEntity: string
{
    case Tickets = 'tickets';
    case Assets = 'assets';
    case Contracts = 'contracts';
    case Locations = 'locations';
}
