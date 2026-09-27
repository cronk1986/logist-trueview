<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\CustomView\SetDefaultCustomView;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class SetDefaultCustomViewRequest extends FormRequest
{
    public function toCommand(int $id): SetDefaultCustomView
    {
        return new SetDefaultCustomView(userId: CurrentAccessToken::userId(request: $this), viewId: $id);
    }
}
