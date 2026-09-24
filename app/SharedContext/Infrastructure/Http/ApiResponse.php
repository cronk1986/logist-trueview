<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;
use stdClass;

final class ApiResponse
{
    private const VERSION = '1.0';

    public static function fromQueryResponse(QueryResponse $response, string $message, int $status = 200): JsonResponse
    {
        return self::build(success: true, data: $response->data(), message: $message, status: $status, meta: $response->meta()?->toArray() ?? []);
    }

    public static function success(string $message, int $status = 200): JsonResponse
    {
        return self::build(success: true, data: [], message: $message, status: $status, meta: []);
    }

    /**
     * @param array<mixed> $data
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $message, array $data = [], array $headers = []): JsonResponse
    {
        return self::build(success: false, data: $data, message: $message, status: $status, meta: [], headers: $headers);
    }

    /**
     * @param array<mixed> $data
     * @param array<string, scalar|null> $meta
     * @param array<string, string> $headers
     */
    private static function build(
        bool $success,
        array $data,
        string $message,
        int $status,
        array $meta,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse(
            data: [
                'success' => $success,
                'data' => $data === [] ? new stdClass() : $data,
                'message' => $message,
                'meta' => [
                    'timestamp' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
                    'version' => self::VERSION,
                    ...$meta,
                ],
            ],
            status: $status,
            headers: $headers,
        );
    }
}
