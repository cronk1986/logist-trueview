<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Application\Command\AccessToken\LogOut;
use Logis\LogisContext\Application\Command\AccessToken\LogOutHandler;
use Logis\LogisContext\Domain\Event\AccessToken\AccessTokenRevoked;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\AccessTokenMother;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\InMemoryAccessTokenRepository;

final class LogOutHandlerTest extends TestCase
{
    private InMemoryAccessTokenRepository $accessTokens;
    private LogOutHandler $handler;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();
        $this->now = CarbonImmutable::parse(time: '2026-09-25 10:00:00', timezone: 'UTC');
        CarbonImmutable::setTestNow(testNow: $this->now);

        $this->accessTokens = new InMemoryAccessTokenRepository();
        $this->handler = new LogOutHandler(accessTokens: $this->accessTokens);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_it_revokes_the_current_token_and_its_predecessor_in_grace_period(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'predecessor', expiresAt: $this->now->addSeconds(5), rotatedAt: $this->now));
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token', rotatedFrom: 'predecessor'));
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'other-device'));

        $this->handler->handle(command: new LogOut(currentTokenHash: PlainTextToken::fromString(value: 'current-token')->hash()->value()));

        $this->assertNull($this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'current-token')->hash()));
        $this->assertNull($this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'predecessor')->hash()));
        $this->assertNotNull($this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'other-device')->hash()));
        $this->assertNotNull(DomainEventPublisher::lastEventOfType(domainEventType: AccessTokenRevoked::class));
    }

    public function test_an_expired_predecessor_is_left_for_the_cleanup(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'predecessor', expiresAt: $this->now->subSecond(), rotatedAt: $this->now));
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token', rotatedFrom: 'predecessor'));

        $this->handler->handle(command: new LogOut(currentTokenHash: PlainTextToken::fromString(value: 'current-token')->hash()->value()));

        $this->assertNull($this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'current-token')->hash()));
        $this->assertNotNull($this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'predecessor')->hash()));
    }

    public function test_it_revokes_the_successors_issued_by_concurrent_requests(): void
    {
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'current-token', expiresAt: $this->now->addSeconds(5), rotatedAt: $this->now));
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'successor', rotatedFrom: 'current-token', rotatedAt: $this->now));
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'successor-of-successor', rotatedFrom: 'successor'));

        $this->handler->handle(command: new LogOut(currentTokenHash: PlainTextToken::fromString(value: 'current-token')->hash()->value()));

        $this->assertSame([], $this->accessTokens->all());
    }

    public function test_an_unknown_token_cannot_log_out(): void
    {
        $this->expectException(AccessTokenNotFoundException::class);

        $this->handler->handle(command: new LogOut(currentTokenHash: PlainTextToken::fromString(value: 'unknown')->hash()->value()));
    }
}
