<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\User;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class UserMapper
{
    public function toDomain(UserModel $model): User
    {
        return new User(
            id: UserId::fromInt(value: $model->id),
            name: $model->name,
            email: Email::fromString(value: $model->email),
            passwordHash: $model->password,
        );
    }
}
