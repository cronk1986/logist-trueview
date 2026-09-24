<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class UserMother
{
    public static function create(
        int $id = 1,
        string $name = 'Demo',
        string $email = 'demo@logis.test',
        string $password = 'password',
    ): User {
        return new User(id: UserId::fromInt(value: $id), name: $name, email: Email::fromString(value: $email), passwordHash: InMemoryPasswordHasher::hash(password: $password));
    }
}
