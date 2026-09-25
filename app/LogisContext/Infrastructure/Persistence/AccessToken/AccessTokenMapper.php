<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\AccessToken;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class AccessTokenMapper
{
    public function toDomain(AccessTokenModel $model): AccessToken
    {
        return new AccessToken(
            hash: TokenHash::fromString(value: $model->token),
            userId: UserId::fromInt(value: $model->tokenable_id),
            ipAddress: $model->ip_address ?? '',
            userAgent: $model->user_agent ?? '',
            expiresAt: CarbonImmutable::instance(date: $model->expires_at)->utc(),
            rotatedAt: $this->toUtcOrNull(date: $model->rotated_at),
            rotatedFrom: $this->rotatedFrom(model: $model),
        );
    }

    private function toUtcOrNull(?DateTimeInterface $date): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        return CarbonImmutable::instance(date: $date)->utc();
    }

    private function rotatedFrom(AccessTokenModel $model): ?TokenHash
    {
        if ($model->rotated_from === null) {
            return null;
        }

        return TokenHash::fromString(value: $model->rotated_from);
    }
}
