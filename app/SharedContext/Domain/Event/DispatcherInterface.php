<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\Event;

interface DispatcherInterface
{
    public function handle(DomainEvent $event): void;
}
