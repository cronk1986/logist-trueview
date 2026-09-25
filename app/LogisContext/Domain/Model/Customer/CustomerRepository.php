<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Customer;

use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;

interface CustomerRepository
{
    public function findById(CustomerId $id): ?Customer;
}
