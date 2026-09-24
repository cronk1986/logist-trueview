<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    private array $users = [];

    public function add(User $user): void
    {
        $this->users[$user->id()->value()] = $user;
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if (strcasecmp(string1: $user->email()->value(), string2: $email->value()) === 0) {
                return $user;
            }
        }

        return null;
    }
}
