<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRevoked;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRotated;
use Logis\LogisContext\Domain\Event\User\UserLoggedIn;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenAlreadyRotatedException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;

final class AccessToken
{
    public function __construct(
        private readonly TokenHash $hash,
        private readonly UserId $userId,
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private CarbonImmutable $expiresAt,
        private ?CarbonImmutable $rotatedAt = null,
        private readonly ?TokenHash $rotatedFrom = null,
    ) {
    }

    public static function issueOnLogin(
        TokenHash $hash,
        UserId $userId,
        string $ipAddress,
        string $userAgent,
        int $ttlMinutes,
    ): self {
        $accessToken = new self(hash: $hash, userId: $userId, ipAddress: $ipAddress, userAgent: $userAgent, expiresAt: self::now()->addMinutes($ttlMinutes));

        DomainEventPublisher::publish(event: new UserLoggedIn(userId: $userId));

        return $accessToken;
    }

    public function rotate(
        TokenHash $successorHash,
        string $ipAddress,
        string $userAgent,
        int $ttlMinutes,
        int $graceSeconds,
    ): self {
        if ($this->isRotated()) {
            throw AccessTokenAlreadyRotatedException::create();
        }

        $now = self::now();
        $this->rotatedAt = $now;
        $this->expiresAt = $this->expiresAt->min(date: $now->addSeconds($graceSeconds));

        $successor = new self(
            hash: $successorHash,
            userId: $this->userId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            expiresAt: $now->addMinutes($ttlMinutes),
            rotatedFrom: $this->hash,
        );

        DomainEventPublisher::publish(event: new AccessTokenRotated(userId: $this->userId));

        return $successor;
    }

    public function revoke(): void
    {
        DomainEventPublisher::publish(event: new AccessTokenRevoked(userId: $this->userId));
    }

    public function isRotated(): bool
    {
        return $this->rotatedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->lessThan(date: self::now());
    }

    public function hash(): TokenHash
    {
        return $this->hash;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function expiresAt(): CarbonImmutable
    {
        return $this->expiresAt;
    }

    public function rotatedAt(): ?CarbonImmutable
    {
        return $this->rotatedAt;
    }

    public function rotatedFrom(): ?TokenHash
    {
        return $this->rotatedFrom;
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->utc();
    }
}
