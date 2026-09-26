<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\CustomView\SetDefaultCustomView;
use Logis\LogisContext\Application\Query\CustomView\GetCustomView;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class SetDefaultCustomViewRequest extends FormRequest
{
    public function toCommand(int $id): SetDefaultCustomView
    {
        return new SetDefaultCustomView(userId: $this->userId(), viewId: $id);
    }

    public function toQuery(int $id): GetCustomView
    {
        return new GetCustomView(userId: $this->userId(), viewId: $id);
    }

    private function userId(): int
    {
        return CurrentAccessToken::of(request: $this)->tokenable_id;
    }
}
