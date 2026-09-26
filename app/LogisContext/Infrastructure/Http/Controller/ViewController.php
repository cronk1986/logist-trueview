<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\CreateCustomViewRequest;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\DeleteCustomViewRequest;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\ListCustomViewsRequest;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\ShowCustomViewRequest;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\UpdateCustomViewRequest;
use Logis\SharedContext\Infrastructure\Http\AbstractController;
use Logis\SharedContext\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final class ViewController extends AbstractController
{
    public function index(ListCustomViewsRequest $request): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery());

        return ApiResponse::fromQueryResponse(response: $response, message: 'Views');
    }

    public function store(CreateCustomViewRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(command: $request->toCommand());
        $response = $this->queryBus->ask(query: $request->toQuery());

        return ApiResponse::fromQueryResponse(response: $response, message: 'View created', status: Response::HTTP_CREATED);
    }

    public function show(ShowCustomViewRequest $request, int $id): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery(id: $id));

        return ApiResponse::fromQueryResponse(response: $response, message: 'View details');
    }

    public function update(UpdateCustomViewRequest $request, int $id): JsonResponse
    {
        $this->commandBus->dispatch(command: $request->toCommand(id: $id));
        $response = $this->queryBus->ask(query: $request->toQuery(id: $id));

        return ApiResponse::fromQueryResponse(response: $response, message: 'View updated');
    }

    public function destroy(DeleteCustomViewRequest $request, int $id): JsonResponse
    {
        $this->commandBus->dispatch(command: $request->toCommand(id: $id));

        return ApiResponse::success(message: 'View deleted');
    }
}
