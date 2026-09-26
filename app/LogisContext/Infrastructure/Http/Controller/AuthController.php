<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Logis\LogisContext\Application\Query\AccessToken\GetAccessTokenByToken;
use Logis\LogisContext\Infrastructure\Http\PlainTextTokenGenerator;
use Logis\LogisContext\Infrastructure\Http\Request\Auth\LoginRequest;
use Logis\LogisContext\Infrastructure\Http\Request\Auth\LogoutRequest;
use Logis\LogisContext\Infrastructure\Http\Request\Auth\MeRequest;
use Logis\LogisContext\Infrastructure\Http\Request\Auth\RefreshTokenRequest;
use Logis\SharedContext\Infrastructure\Bus\CommandBusInterface;
use Logis\SharedContext\Infrastructure\Bus\QueryBusInterface;
use Logis\SharedContext\Infrastructure\Http\AbstractController;
use Logis\SharedContext\Infrastructure\Http\ApiResponse;

final class AuthController extends AbstractController
{
    public function __construct(
        CommandBusInterface $commandBus,
        QueryBusInterface $queryBus,
        private readonly PlainTextTokenGenerator $tokenGenerator,
    ) {
        parent::__construct(commandBus: $commandBus, queryBus: $queryBus);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $plainTextToken = $this->tokenGenerator->generate();

        $this->commandBus->dispatch(command: $request->toCommand(plainTextToken: $plainTextToken));

        $response = $this->queryBus->ask(query: new GetAccessTokenByToken(token: $plainTextToken->value()));

        return ApiResponse::fromQueryResponse(response: $response, message: 'Logged in');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $plainTextToken = $this->tokenGenerator->generate();

        $this->commandBus->dispatch(command: $request->toCommand(plainTextToken: $plainTextToken));

        $response = $this->queryBus->ask(query: new GetAccessTokenByToken(token: $plainTextToken->value()));

        return ApiResponse::fromQueryResponse(response: $response, message: 'Token refreshed');
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(command: $request->toCommand());

        return ApiResponse::success(message: 'Logged out');
    }

    public function me(MeRequest $request): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery());

        return ApiResponse::fromQueryResponse(response: $response, message: 'Authenticated user');
    }
}
