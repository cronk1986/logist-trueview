<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\LogIn;

use Logis\LogisContext\Domain\Exception\User\InvalidCredentialsException;
use Logis\LogisContext\Domain\Model\AccessToken\AccessToken;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\Model\User\PasswordHasher;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;
use Logis\LogisContext\Domain\ValueObject\User\Email;

final class LogInHandler
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwordHasher,
        private readonly AccessTokenRepository $accessTokens,
        private readonly int $ttlMinutes,
    ) {
    }

    public function handle(LogIn $command): void
    {
        $user = $this->authenticate(email: Email::fromString(value: $command->email), password: $command->password);

        $accessToken = AccessToken::issueOnLogin(
            hash: TokenHash::fromString(value: $command->tokenHash),
            userId: $user->id(),
            ipAddress: $command->ipAddress,
            userAgent: $command->userAgent,
            ttlMinutes: $this->ttlMinutes,
        );

        $this->accessTokens->save(accessToken: $accessToken);
    }

    private function authenticate(Email $email, string $password): User
    {
        $user = $this->users->findByEmail(email: $email);

        if ($user === null) {
            $this->passwordHasher->verifyAgainstDummyHash(password: $password);

            throw InvalidCredentialsException::create();
        }

        if (! $this->passwordHasher->verify(password: $password, hash: $user->passwordHash())) {
            throw InvalidCredentialsException::create();
        }

        return $user;
    }
}
