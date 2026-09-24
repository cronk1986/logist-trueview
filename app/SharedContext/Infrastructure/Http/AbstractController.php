<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Http;

use Logis\SharedContext\Infrastructure\Bus\CommandBusInterface;
use Logis\SharedContext\Infrastructure\Bus\QueryBusInterface;

abstract class AbstractController
{
    public function __construct(
        protected readonly CommandBusInterface $commandBus,
        protected readonly QueryBusInterface $queryBus,
    ) {
    }
}
