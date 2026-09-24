<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class EloquentAccessTokenRepository implements AccessTokenRepository
{
    private const TOKEN_NAME = 'api';

    public function save(AccessToken $accessToken): void
    {
        $model = new AccessTokenModel();
        $model->forceFill(attributes: [
            'tokenable_type' => (new UserModel())->getMorphClass(),
            'tokenable_id' => $accessToken->userId()->value(),
            'name' => self::TOKEN_NAME,
            'token' => $accessToken->hash()->value(),
            'abilities' => ['*'],
            'ip_address' => $accessToken->ipAddress(),
            'user_agent' => $accessToken->userAgent(),
            'expires_at' => $accessToken->expiresAt(),
            'rotated_at' => $accessToken->rotatedAt(),
            'rotated_from' => $accessToken->rotatedFrom()?->value(),
        ]);
        $model->save();
    }

    public function findByHash(TokenHash $hash): ?AccessToken
    {
        $model = AccessTokenModel::query()->where(column: 'token', operator: $hash->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain(model: $model);
    }

    public function claimRotation(AccessToken $rotatedAccessToken): bool
    {
        $claimed = AccessTokenModel::query()
            ->where(column: 'token', operator: $rotatedAccessToken->hash()->value())
            ->whereNull('rotated_at')
            ->update(values: [
                'rotated_at' => $rotatedAccessToken->rotatedAt(),
                'expires_at' => $rotatedAccessToken->expiresAt(),
            ]);

        return $claimed === 1;
    }

    public function findRotatedFrom(TokenHash $hash): array
    {
        $models = AccessTokenModel::query()->where(column: 'rotated_from', operator: $hash->value())->get();

        return array_values(array: $models->map(callback: fn (AccessTokenModel $model): AccessToken => $this->toDomain(model: $model))->all());
    }

    public function delete(TokenHash $hash): void
    {
        AccessTokenModel::query()->where(column: 'token', operator: $hash->value())->delete();
    }

    private function toDomain(AccessTokenModel $model): AccessToken
    {
        return new AccessToken(
            hash: TokenHash::fromString(value: $model->token),
            userId: UserId::fromInt(value: $model->tokenable_id),
            ipAddress: $model->ip_address ?? '',
            userAgent: $model->user_agent ?? '',
            expiresAt: CarbonImmutable::instance(date: $model->expires_at)->utc(),
            rotatedAt: $this->toUtcOrNull(date: $model->rotated_at),
            rotatedFrom: $model->rotated_from === null ? null : TokenHash::fromString(value: $model->rotated_from),
        );
    }

    private function toUtcOrNull(?DateTimeInterface $date): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        return CarbonImmutable::instance(date: $date)->utc();
    }
}
