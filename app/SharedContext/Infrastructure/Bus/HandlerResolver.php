<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

use Closure;
use Illuminate\Contracts\Container\Container;

final class HandlerResolver
{
    public function __construct(private readonly Container $container)
    {
    }

    public function resolve(object $message): Closure
    {
        $handlerClass = $message::class . 'Handler';

        if (! class_exists(class: $handlerClass) || ! method_exists(object_or_class: $handlerClass, method: 'handle')) {
            throw HandlerNotFoundException::for(message: $message, handlerClass: $handlerClass);
        }

        $handler = $this->container->make(abstract: $handlerClass);

        return $handler->handle(...);
    }
}
