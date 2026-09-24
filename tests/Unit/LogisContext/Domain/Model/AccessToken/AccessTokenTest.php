<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRevoked;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRotated;
use Logis\LogisContext\Domain\Event\User\UserLoggedIn;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenAlreadyRotatedException;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class AccessTokenTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();
        $this->now = CarbonImmutable::parse(time: '2026-09-25 10:00:00', timezone: 'UTC');
        CarbonImmutable::setTestNow(testNow: $this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_issuing_on_login_sets_the_expiry_and_raises_user_logged_in(): void
    {
        $accessToken = AccessToken::issueOnLogin(
            hash: PlainTextToken::fromString(value: 'token')->hash(),
            userId: UserId::fromInt(value: 1),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            ttlMinutes: 60,
        );

        $this->assertEquals($this->now->addMinutes(60), $accessToken->expiresAt());
        $this->assertFalse($accessToken->isRotated());
        $this->assertSame(1, DomainEventPublisher::lastEventOfType(domainEventType: UserLoggedIn::class)?->userId()->value());
    }

    public function test_rotating_puts_the_token_in_its_grace_period_and_returns_the_successor(): void
    {
        $accessToken = AccessTokenMother::create();

        $successor = $accessToken->rotate(successorHash: PlainTextToken::fromString(value: 'successor')->hash(), ipAddress: '10.0.0.1', userAgent: 'Browser', ttlMinutes: 60, graceSeconds: 10);

        $this->assertEquals($this->now, $accessToken->rotatedAt());
        $this->assertEquals($this->now->addSeconds(10), $accessToken->expiresAt());
        $this->assertTrue($successor->hash()->equals(other: PlainTextToken::fromString(value: 'successor')->hash()));
        $this->assertTrue($successor->rotatedFrom()?->equals(other: $accessToken->hash()));
        $this->assertEquals($this->now->addMinutes(60), $successor->expiresAt());
        $this->assertSame('10.0.0.1', $successor->ipAddress());
        $this->assertSame('Browser', $successor->userAgent());
        $this->assertNotNull(DomainEventPublisher::lastEventOfType(domainEventType: AccessTokenRotated::class));
    }

    public function test_the_grace_period_never_extends_the_expiry(): void
    {
        $accessToken = AccessTokenMother::create(expiresAt: $this->now->addSeconds(3));

        $accessToken->rotate(successorHash: PlainTextToken::fromString(value: 'successor')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);

        $this->assertEquals($this->now->addSeconds(3), $accessToken->expiresAt());
    }

    public function test_a_rotated_token_cannot_be_rotated_again(): void
    {
        $accessToken = AccessTokenMother::create(rotatedAt: $this->now);

        $this->expectException(AccessTokenAlreadyRotatedException::class);

        $accessToken->rotate(successorHash: PlainTextToken::fromString(value: 'successor')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);
    }

    public function test_revoking_raises_access_token_revoked(): void
    {
        AccessTokenMother::create(userId: 5)->revoke();

        $this->assertSame(5, DomainEventPublisher::lastEventOfType(domainEventType: AccessTokenRevoked::class)?->userId()->value());
    }

    public function test_a_token_is_expired_once_its_expiry_has_passed(): void
    {
        $this->assertTrue(AccessTokenMother::create(expiresAt: $this->now->subSecond())->isExpired());
        $this->assertFalse(AccessTokenMother::create(expiresAt: $this->now)->isExpired());
    }
}
