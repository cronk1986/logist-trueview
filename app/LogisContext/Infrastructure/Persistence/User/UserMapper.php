<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\User;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class UserMapper
{
    public function __construct(private readonly CustomerMapper $customerMapper)
    {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations());
    }

    public function toDomain(UserModel $model): User
    {
        return new User(
            id: UserId::fromInt(value: $model->id),
            name: $model->name,
            email: Email::fromString(value: $model->email),
            passwordHash: $model->password,
            customer: $this->customer(model: $model),
        );
    }

    private function customer(UserModel $model): ?Customer
    {
        if ($model->customer === null) {
            return null;
        }

        return $this->customerMapper->toDomain(model: $model->customer);
    }
}
