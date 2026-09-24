<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Query\GetUser\GetUser;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class MeRequest extends FormRequest
{
    public function toQuery(): GetUser
    {
        return new GetUser(userId: CurrentAccessToken::of(request: $this)->tokenable_id);
    }
}
