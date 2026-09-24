<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

interface QueryBusInterface
{
    public function ask(object $query): QueryResponse;
}
