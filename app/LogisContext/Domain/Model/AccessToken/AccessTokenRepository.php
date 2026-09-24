<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\AccessToken;

use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;

interface AccessTokenRepository
{
    public function save(AccessToken $accessToken): void;

    public function findByHash(TokenHash $hash): ?AccessToken;

    public function claimRotation(AccessToken $rotatedAccessToken): bool;

    /** @return list<AccessToken> */
    public function findRotatedFrom(TokenHash $hash): array;

    public function delete(TokenHash $hash): void;
}
