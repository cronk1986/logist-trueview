<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

interface CommandBusInterface
{
    public function dispatch(object $command): void;
}
