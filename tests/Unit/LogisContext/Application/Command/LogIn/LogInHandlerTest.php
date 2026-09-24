<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Command\LogIn;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Application\Command\LogIn\LogIn;
use Logis\LogisContext\Application\Command\LogIn\LogInHandler;
use Logis\LogisContext\Domain\Event\User\UserLoggedIn;
use Logis\LogisContext\Domain\Exception\User\InvalidCredentialsException;
use Logis\LogisContext\Domain\Exception\User\InvalidEmailException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\InMemoryAccessTokenRepository;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryPasswordHasher;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class LogInHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemoryPasswordHasher $passwordHasher;
    private InMemoryAccessTokenRepository $accessTokens;
    private LogInHandler $handler;
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        DomainEventPublisher::reset();
        $this->now = CarbonImmutable::parse(time: '2026-09-25 10:00:00', timezone: 'UTC');
        CarbonImmutable::setTestNow(testNow: $this->now);

        $this->users = new InMemoryUserRepository();
        $this->users->add(user: UserMother::create(id: 1, email: 'demo@logis.test', password: 'password'));
        $this->passwordHasher = new InMemoryPasswordHasher();
        $this->accessTokens = new InMemoryAccessTokenRepository();
        $this->handler = new LogInHandler(users: $this->users, passwordHasher: $this->passwordHasher, accessTokens: $this->accessTokens, ttlMinutes: 60);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_valid_credentials_store_the_access_token_and_raise_user_logged_in(): void
    {
        $this->handler->handle(command: $this->command(email: 'demo@logis.test', password: 'password'));

        $accessToken = $this->accessTokens->findByHash(hash: PlainTextToken::fromString(value: 'new-token')->hash());

        $this->assertNotNull($accessToken);
        $this->assertSame(1, $accessToken->userId()->value());
        $this->assertSame('127.0.0.1', $accessToken->ipAddress());
        $this->assertSame('PHPUnit', $accessToken->userAgent());
        $this->assertEquals($this->now->addMinutes(60), $accessToken->expiresAt());
        $this->assertSame(1, DomainEventPublisher::lastEventOfType(domainEventType: UserLoggedIn::class)?->userId()->value());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->handler->handle(command: $this->command(email: 'demo@logis.test', password: 'wrong'));
    }

    public function test_an_unknown_email_is_rejected_after_a_dummy_verification(): void
    {
        try {
            $this->handler->handle(command: $this->command(email: 'unknown@logis.test', password: 'password'));
            $this->fail('InvalidCredentialsException was not thrown.');
        } catch (InvalidCredentialsException) {
            $this->assertSame(1, $this->passwordHasher->dummyVerifications());
        }

        $this->assertSame([], $this->accessTokens->all());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->handler->handle(command: $this->command(email: 'not-an-email', password: 'password'));
    }

    private function command(string $email, string $password): LogIn
    {
        return new LogIn(
            email: $email,
            password: $password,
            tokenHash: PlainTextToken::fromString(value: 'new-token')->hash()->value(),
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
        );
    }
}
