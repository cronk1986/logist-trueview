<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\Persistence\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\AccessTokenMapper;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\AccessTokenModel;
use Tests\TestCase;

final class AccessTokenMapperTest extends TestCase
{
    public function test_it_maps_a_fresh_token(): void
    {
        $hash = PlainTextToken::fromString(value: 'fresh')->hash()->value();
        $model = $this->model(attributes: [
            'token' => $hash,
            'tokenable_id' => 3,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => CarbonImmutable::parse(time: '2026-09-25 11:00:00', timezone: 'UTC'),
            'rotated_at' => null,
            'rotated_from' => null,
        ]);

        $accessToken = (new AccessTokenMapper())->toDomain(model: $model);

        $this->assertSame($hash, $accessToken->hash()->value());
        $this->assertSame(3, $accessToken->userId()->value());
        $this->assertSame('10.0.0.1', $accessToken->ipAddress());
        $this->assertSame('PHPUnit', $accessToken->userAgent());
        $this->assertSame('2026-09-25 11:00:00', $accessToken->expiresAt()->format(format: 'Y-m-d H:i:s'));
        $this->assertNull($accessToken->rotatedAt());
        $this->assertNull($accessToken->rotatedFrom());
    }

    public function test_it_maps_a_rotated_token_and_missing_metadata(): void
    {
        $predecessor = PlainTextToken::fromString(value: 'predecessor')->hash()->value();
        $model = $this->model(attributes: [
            'token' => PlainTextToken::fromString(value: 'rotated')->hash()->value(),
            'tokenable_id' => 3,
            'ip_address' => null,
            'user_agent' => null,
            'expires_at' => CarbonImmutable::parse(time: '2026-09-25 10:00:10', timezone: 'UTC'),
            'rotated_at' => CarbonImmutable::parse(time: '2026-09-25 10:00:00', timezone: 'UTC'),
            'rotated_from' => $predecessor,
        ]);

        $accessToken = (new AccessTokenMapper())->toDomain(model: $model);

        $this->assertSame('', $accessToken->ipAddress());
        $this->assertSame('', $accessToken->userAgent());
        $this->assertSame('2026-09-25 10:00:00', $accessToken->rotatedAt()?->format(format: 'Y-m-d H:i:s'));
        $this->assertSame($predecessor, $accessToken->rotatedFrom()?->value());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function model(array $attributes): AccessTokenModel
    {
        $model = new AccessTokenModel();
        $model->forceFill(attributes: $attributes);

        return $model;
    }
}
