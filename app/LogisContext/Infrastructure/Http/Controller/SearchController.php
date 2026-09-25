<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Controller;

use Illuminate\Http\JsonResponse;
use Logis\LogisContext\Infrastructure\Http\Request\Search\SearchRequest;
use Logis\SharedContext\Infrastructure\Http\AbstractController;
use Logis\SharedContext\Infrastructure\Http\ApiResponse;

final class SearchController extends AbstractController
{
    public function search(SearchRequest $request): JsonResponse
    {
        $response = $this->queryBus->ask(query: $request->toQuery());

        return ApiResponse::fromQueryResponse(response: $response, message: 'Search results');
    }
}
