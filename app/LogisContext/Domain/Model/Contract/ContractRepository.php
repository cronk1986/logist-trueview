<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Contract;

use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;

interface ContractRepository
{
    public function findById(ContractId $id): ?Contract;
}
