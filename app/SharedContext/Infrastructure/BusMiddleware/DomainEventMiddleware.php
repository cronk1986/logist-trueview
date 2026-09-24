<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\BusMiddleware;

use Closure;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use Throwable;

final class DomainEventMiddleware
{
    /**
     * @param Closure(object): mixed $next
     */
    public function handle(object $command, Closure $next): mixed
    {
        $publisher = DomainEventPublisher::instance();

        try {
            $result = $next($command);

            while ($publisher->hasEventsToPublish()) {
                $publisher->publishAllEvents();
            }

            return $result;
        } catch (Throwable $exception) {
            $publisher->clear();

            throw $exception;
        }
    }
}
