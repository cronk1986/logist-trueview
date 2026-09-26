<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\ListCustomViewsRequest;
use Logis\LogisContext\Infrastructure\Http\Request\CustomView\ShowCustomViewRequest;
use Logis\SharedContext\Infrastructure\Http\AbstractController;
use Logis\SharedContext\Infrastructure\Http\ApiResponse;

final class ViewController extends AbstractController
{
    public function index(ListCustomViewsRequest $request): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery());

        return ApiResponse::fromQueryResponse(response: $response, message: 'Views');
    }

    public function show(ShowCustomViewRequest $request, int $id): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery(id: $id));

        return ApiResponse::fromQueryResponse(response: $response, message: 'View details');
    }
}
