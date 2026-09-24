<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\User;

interface PasswordHasher
{
    public function verify(string $password, string $hash): bool;

    public function verifyAgainstDummyHash(string $password): void;
}
