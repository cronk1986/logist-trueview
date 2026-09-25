<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Logis\SharedContext\Domain\Exception\ConflictException;
use Logis\SharedContext\Domain\Exception\InvalidValueException;
use Logis\SharedContext\Domain\Exception\NotFoundException;
use Logis\SharedContext\Domain\Exception\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    private const DOMAIN_STATUSES = [
        UnauthorizedException::class => Response::HTTP_UNAUTHORIZED,
        NotFoundException::class => Response::HTTP_NOT_FOUND,
        ConflictException::class => Response::HTTP_CONFLICT,
        InvalidValueException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (!$request->is('api/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::error(status: Response::HTTP_UNPROCESSABLE_ENTITY, message: 'Validation failed', data: $exception->errors());
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::error(status: Response::HTTP_UNAUTHORIZED, message: 'Unauthenticated');
        }

        foreach (self::DOMAIN_STATUSES as $exceptionClass => $status) {
            if ($exception instanceof $exceptionClass) {
                return ApiResponse::error(status: $status, message: $exception->getMessage());
            }
        }

        if ($exception instanceof HttpExceptionInterface) {
            return $this->renderHttpException(exception: $exception);
        }

        return ApiResponse::error(status: Response::HTTP_INTERNAL_SERVER_ERROR, message: 'Server error');
    }

    /**
     * @return list<class-string<Throwable>>
     */
    public static function expectedExceptions(): array
    {
        return array_keys(array: self::DOMAIN_STATUSES);
    }

    private function renderHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        /** @var array<string, string> $headers */
        $headers = $exception->getHeaders();

        return ApiResponse::error(status: $status, message: Response::$statusTexts[$status] ?? 'Error', data: [], headers: $headers);
    }
}
