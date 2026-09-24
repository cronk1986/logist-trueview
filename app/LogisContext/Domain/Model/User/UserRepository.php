<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;
}
