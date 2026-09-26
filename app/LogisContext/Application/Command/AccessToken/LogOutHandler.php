<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\AccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;

final class LogOutHandler
{
    public function __construct(private readonly AccessTokenRepository $accessTokens)
    {
    }

    public function handle(LogOut $command): void
    {
        $currentAccessToken = $this->accessTokens->findByHash(hash: TokenHash::fromString(value: $command->currentTokenHash));

        if ($currentAccessToken === null) {
            throw AccessTokenNotFoundException::create();
        }

        $this->revokePredecessorInGracePeriod(accessToken: $currentAccessToken);
        $this->revokeWithSuccessors(accessToken: $currentAccessToken);
    }

    private function revokePredecessorInGracePeriod(AccessToken $accessToken): void
    {
        $predecessorHash = $accessToken->rotatedFrom();

        if ($predecessorHash === null) {
            return;
        }

        $predecessor = $this->accessTokens->findByHash(hash: $predecessorHash);

        if ($predecessor === null || $predecessor->isExpired()) {
            return;
        }

        $this->revoke(accessToken: $predecessor);
    }

    private function revokeWithSuccessors(AccessToken $accessToken): void
    {
        $this->revoke(accessToken: $accessToken);

        foreach ($this->accessTokens->findRotatedFrom(hash: $accessToken->hash()) as $successor) {
            $this->revokeWithSuccessors(accessToken: $successor);
        }
    }

    private function revoke(AccessToken $accessToken): void
    {
        $accessToken->revoke();
        $this->accessTokens->delete(hash: $accessToken->hash());
    }
}
