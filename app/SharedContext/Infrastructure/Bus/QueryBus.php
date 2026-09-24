<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Bus;

use Logis\SharedContext\Domain\QueryResponse\QueryResponse;
use UnexpectedValueException;

final class QueryBus implements QueryBusInterface
{
    public function __construct(private readonly HandlerResolver $handlers)
    {
    }

    public function ask(object $query): QueryResponse
    {
        $response = ($this->handlers->resolve(message: $query))($query);

        if (! $response instanceof QueryResponse) {
            throw new UnexpectedValueException(message: sprintf(
                'Handler for %s must return %s, %s returned.',
                $query::class,
                QueryResponse::class,
                get_debug_type(value: $response),
            ));
        }

        return $response;
    }
}
