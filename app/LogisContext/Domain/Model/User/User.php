<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private readonly string $name,
        private readonly Email $email,
        private readonly string $passwordHash,
    ) {
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}
