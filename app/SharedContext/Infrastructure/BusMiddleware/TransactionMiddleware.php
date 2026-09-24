<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\BusMiddleware;

use Closure;
use Illuminate\Database\ConnectionInterface;

final class TransactionMiddleware
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    /**
     * @param Closure(object): mixed $next
     */
    public function handle(object $command, Closure $next): mixed
    {
        return $this->connection->transaction(callback: fn () => $next($command));
    }
}
