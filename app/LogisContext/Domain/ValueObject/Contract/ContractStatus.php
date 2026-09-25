<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Contract;

enum ContractStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
