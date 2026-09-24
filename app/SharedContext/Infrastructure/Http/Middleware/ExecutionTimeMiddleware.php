<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

final class ExecutionTimeMiddleware
{
    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(as_number: true);

        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $body = $response->getData();

        if (! $body instanceof stdClass) {
            return $response;
        }

        $meta = isset($body->meta) && $body->meta instanceof stdClass ? $body->meta : new stdClass();
        $meta->execution_time_ms = intdiv(num1: hrtime(as_number: true) - $start, num2: self::NANOSECONDS_PER_MILLISECOND);
        $body->meta = $meta;

        return $response->setData(data: $body);
    }
}
