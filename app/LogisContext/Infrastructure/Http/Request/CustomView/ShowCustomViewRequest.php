<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Query\CustomView\GetCustomView;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class ShowCustomViewRequest extends FormRequest
{
    public function toQuery(int $id): GetCustomView
    {
        return new GetCustomView(userId: CurrentAccessToken::userId(request: $this), viewId: $id);
    }
}
