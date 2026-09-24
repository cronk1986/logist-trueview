<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\AccessToken;

use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;

final class InMemoryAccessTokenRepository implements AccessTokenRepository
{
    /** @var array<string, AccessToken> */
    private array $accessTokens = [];

    public function save(AccessToken $accessToken): void
    {
        $this->accessTokens[$accessToken->hash()->value()] = clone $accessToken;
    }

    public function findByHash(TokenHash $hash): ?AccessToken
    {
        $accessToken = $this->accessTokens[$hash->value()] ?? null;

        if ($accessToken === null) {
            return null;
        }

        return clone $accessToken;
    }

    public function claimRotation(AccessToken $rotatedAccessToken): bool
    {
        $stored = $this->accessTokens[$rotatedAccessToken->hash()->value()] ?? null;

        if ($stored === null || $stored->isRotated()) {
            return false;
        }

        $this->save(accessToken: $rotatedAccessToken);

        return true;
    }

    public function findRotatedFrom(TokenHash $hash): array
    {
        $successors = [];

        foreach ($this->accessTokens as $accessToken) {
            if ($accessToken->rotatedFrom()?->equals(other: $hash) === true) {
                $successors[] = clone $accessToken;
            }
        }

        return $successors;
    }

    public function delete(TokenHash $hash): void
    {
        unset($this->accessTokens[$hash->value()]);
    }

    /** @return list<AccessToken> */
    public function all(): array
    {
        return array_values(array: $this->accessTokens);
    }
}
