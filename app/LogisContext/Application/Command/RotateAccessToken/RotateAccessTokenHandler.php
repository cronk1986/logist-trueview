<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\RotateAccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenAlreadyRotatedException;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;

final class RotateAccessTokenHandler
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokens,
        private readonly int $ttlMinutes,
        private readonly int $graceSeconds,
    ) {
    }

    public function handle(RotateAccessToken $command): void
    {
        $currentAccessToken = $this->accessTokens->findByHash(hash: TokenHash::fromString(value: $command->currentTokenHash));

        if ($currentAccessToken === null) {
            throw AccessTokenNotFoundException::create();
        }

        $successor = $currentAccessToken->rotate(
            successorHash: TokenHash::fromString(value: $command->newTokenHash),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            ttlMinutes: $this->ttlMinutes,
            graceSeconds: $this->graceSeconds,
        );

        if (!$this->accessTokens->claimRotation(rotatedAccessToken: $currentAccessToken)) {
            throw AccessTokenAlreadyRotatedException::create();
        }

        $this->accessTokens->save(accessToken: $successor);
    }
}
