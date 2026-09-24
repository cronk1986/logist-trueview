<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;

final class CommandBus implements CommandBusInterface
{
    /**
     * @param list<class-string> $middleware
     */
    public function __construct(
        private readonly Container $container,
        private readonly HandlerResolver $handlers,
        private readonly array $middleware,
    ) {
    }

    public function dispatch(object $command): void
    {
        $handler = $this->handlers->resolve(message: $command);

        (new Pipeline(container: $this->container))
            ->send(passable: $command)
            ->through(pipes: $this->middleware)
            ->then(destination: fn (object $command) => $handler($command));
    }
}
