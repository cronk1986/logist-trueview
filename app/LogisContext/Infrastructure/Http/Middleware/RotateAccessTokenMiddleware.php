<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Logis\LogisContext\Application\Command\AccessToken\RotateAccessToken;
use Logis\LogisContext\Application\Query\AccessToken\GetAccessTokenByToken;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenAlreadyRotatedException;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;
use Logis\LogisContext\Infrastructure\Http\PlainTextTokenGenerator;
use Logis\SharedContext\Infrastructure\Bus\CommandBusInterface;
use Logis\SharedContext\Infrastructure\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Response;

final class RotateAccessTokenMiddleware
{
    public const HEADER = 'X-Refreshed-Token';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly PlainTextTokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $currentAccessToken = CurrentAccessToken::find(request: $request);

        if ($currentAccessToken === null) {
            return $response;
        }

        $plainTextToken = $this->tokenGenerator->generate();

        try {
            $this->commandBus->dispatch(command: new RotateAccessToken(
                currentTokenHash: $currentAccessToken->token,
                newTokenHash: $plainTextToken->hash()->value(),
                ipAddress: (string) $request->ip(),
                userAgent: (string) $request->userAgent(),
            ));
        } catch (AccessTokenAlreadyRotatedException | AccessTokenNotFoundException) {
            return $response;
        }

        $accessToken = $this->queryBus->ask(query: new GetAccessTokenByToken(token: $plainTextToken->value()));
        $response->headers->set(key: self::HEADER, values: (string) $accessToken->data()['token']);

        return $response;
    }
}
