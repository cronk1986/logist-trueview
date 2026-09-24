<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\RotateAccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Application\Command\RotateAccessToken\RotateAccessToken;
use Logis\LogisContext\Application\Command\RotateAccessToken\RotateAccessTokenHandler;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRotated;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenAlreadyRotatedException;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\AccessTokenMother;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\InMemoryAccessTokenRepository;

final class RotateAccessTokenHandlerTest extends TestCase
{
    private InMemoryAccessTokenRepository $accessTokens;
    private RotateAccessTokenHandler $handler;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();
        $this->now = CarbonImmutable::parse(time: '2026-09-25 10:00:00', timezone: 'UTC');
        CarbonImmutable::setTestNow(testNow: $this->now);

        $this->accessTokens = new InMemoryAccessTokenRepository();
        $this->handler = new RotateAccessTokenHandler(accessTokens: $this->accessTokens, ttlMinutes: 60, graceSeconds: 10);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_it_stores_the_successor_and_puts_the_current_token_in_its_grace_period(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token'));

        $this->handler->handle(command: $this->command(currentToken: 'current-token', newToken: 'new-token'));

        $current = $this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'current-token')->hash());
        $successor = $this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'new-token')->hash());

        $this->assertNotNull($current);
        $this->assertTrue($current->isRotated());
        $this->assertEquals($this->now->addSeconds(10), $current->expiresAt());
        $this->assertNotNull($successor);
        $this->assertTrue($successor->rotatedFrom()?->equals(other: $current->hash()));
        $this->assertEquals($this->now->addMinutes(60), $successor->expiresAt());
        $this->assertNotNull(DomainEventPublisher::lastEventOfType(domainEventType: AccessTokenRotated::class));
    }

    public function test_an_already_rotated_token_is_not_rotated_again(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token'));
        $this->handler->handle(command: $this->command(currentToken: 'current-token', newToken: 'first-successor'));

        $this->expectException(AccessTokenAlreadyRotatedException::class);

        $this->handler->handle(command: $this->command(currentToken: 'current-token', newToken: 'second-successor'));
    }

    public function test_only_one_of_two_concurrent_rotations_is_claimed(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token'));
        $hash = PlainTextToken::fromString(value: 'current-token')->hash();
        $firstRead = $this->accessTokens->findByHash(hash: $hash);
        $secondRead = $this->accessTokens->findByHash(hash: $hash);
        $this->assertNotNull($firstRead);
        $this->assertNotNull($secondRead);

        $firstRead->rotate(successorHash: PlainTextToken::fromString(value: 'first')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);
        $secondRead->rotate(successorHash: PlainTextToken::fromString(value: 'second')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);
        $this->assertTrue($this->accessTokens->claimRotation(rotatedAccessToken: $firstRead));
        $this->assertFalse($this->accessTokens->claimRotation(rotatedAccessToken: $secondRead));
    }

    public function test_an_unknown_token_cannot_be_rotated(): void
    {
        $this->expectException(AccessTokenNotFoundException::class);

        $this->handler->handle(command: $this->command(currentToken: 'unknown-token', newToken: 'new-token'));
    }

    private function command(string $currentToken, string $newToken): RotateAccessToken
    {
        return new RotateAccessToken(
            currentTokenHash: PlainTextToken::fromString(value: $currentToken)->hash()->value(),
            newTokenHash: PlainTextToken::fromString(value: $newToken)->hash()->value(),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
        );
    }
}
