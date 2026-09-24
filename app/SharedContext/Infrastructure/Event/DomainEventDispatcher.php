<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Event;

use Illuminate\Contracts\Events\Dispatcher;
use Logis\SharedContext\Domain\Event\DispatcherInterface;
use Logis\SharedContext\Domain\Event\DomainEvent;

final class DomainEventDispatcher implements DispatcherInterface
{
    public function __construct(private readonly Dispatcher $dispatcher)
    {
    }

    public function handle(DomainEvent $event): void
    {
        $this->dispatcher->dispatch(event: $event);
    }
}
