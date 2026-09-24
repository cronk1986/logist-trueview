<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

use LogicException;

final class HandlerNotFoundException extends LogicException
{
    public static function for(object $message, string $handlerClass): self
    {
        return new self(message: sprintf(
            'No handler found for %s: expected %s with a public handle() method.',
            $message::class,
            $handlerClass,
        ));
    }
}
