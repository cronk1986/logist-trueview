<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\CustomView\DeleteCustomView;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class DeleteCustomViewRequest extends FormRequest
{
    public function toCommand(int $id): DeleteCustomView
    {
        return new DeleteCustomView(userId: CurrentAccessToken::of(request: $this)->tokenable_id, viewId: $id);
    }
}
