<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Logis\LogisContext\Application\Command\RotateAccessToken\RotateAccessToken;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class RefreshTokenRequest extends FormRequest
{
    public function toCommand(PlainTextToken $plainTextToken): RotateAccessToken
    {
        return new RotateAccessToken(
            currentTokenHash: CurrentAccessToken::of(request: $this)->token,
            newTokenHash: $plainTextToken->hash()->value(),
            ipAddress: (string) $this->ip(),
            userAgent: (string) $this->userAgent(),
        );
    }
}
