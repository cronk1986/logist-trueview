<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\AccessToken;

use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;

final readonly class AccessTokenView
{
    private function __construct(public string $token, public string $expiresAt)
    {
    }

    public static function from(PlainTextToken $plainTextToken, AccessToken $accessToken): self
    {
        return new self(token: $plainTextToken->value(), expiresAt: $accessToken->expiresAt()->toIso8601ZuluString());
    }

    /** @return array{token: string, expires_at: string} */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'expires_at' => $this->expiresAt,
        ];
    }
}
