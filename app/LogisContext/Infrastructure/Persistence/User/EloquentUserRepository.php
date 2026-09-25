<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\User;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class EloquentUserRepository implements UserRepository
{
    public function __construct(private readonly UserMapper $mapper)
    {
    }

    public function findById(UserId $id): ?User
    {
        $model = UserModel::query()->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where(column: 'email', operator: $email->value())->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
