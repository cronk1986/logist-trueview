<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class AccessTokenMother
{
    public static function create(
        string $plainTextToken = 'current-token',
        int $userId = 1,
        ?CarbonImmutable $expiresAt = null,
        ?CarbonImmutable $rotatedAt = null,
        ?string $rotatedFrom = null,
    ): AccessToken {
        return new AccessToken(
            hash: PlainTextToken::fromString(value: $plainTextToken)->hash(),
            userId: UserId::fromInt(value: $userId),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            expiresAt: $expiresAt ?? CarbonImmutable::now()->utc()->addHour(),
            rotatedAt: $rotatedAt,
            rotatedFrom: $rotatedFrom === null ? null : PlainTextToken::fromString(value: $rotatedFrom)->hash(),
        );
    }
}
