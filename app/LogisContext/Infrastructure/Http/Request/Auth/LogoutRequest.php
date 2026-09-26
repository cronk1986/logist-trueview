<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\AccessToken\LogOut;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class LogoutRequest extends FormRequest
{
    public function toCommand(): LogOut
    {
        return new LogOut(currentTokenHash: CurrentAccessToken::of(request: $this)->token);
    }
}
