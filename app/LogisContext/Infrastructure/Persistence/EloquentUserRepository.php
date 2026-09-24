<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class EloquentUserRepository implements UserRepository
{
    public function findById(UserId $id): ?User
    {
        $model = UserModel::query()->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->toDomain(model: $model);
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where(column: 'email', operator: $email->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain(model: $model);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: UserId::fromInt(value: $model->id),
            name: $model->name,
            email: Email::fromString(value: $model->email),
            passwordHash: $model->password,
        );
    }
}
