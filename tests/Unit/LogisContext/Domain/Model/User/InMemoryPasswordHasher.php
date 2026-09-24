<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\User;

use Logis\LogisContext\Domain\Model\User\PasswordHasher;

final class InMemoryPasswordHasher implements PasswordHasher
{
    private int $dummyVerifications = 0;

    public static function hash(string $password): string
    {
        return 'hashed:' . $password;
    }

    public function verify(string $password, string $hash): bool
    {
        return self::hash(password: $password) === $hash;
    }

    public function verifyAgainstDummyHash(string $password): void
    {
        $this->dummyVerifications++;
    }

    public function dummyVerifications(): int
    {
        return $this->dummyVerifications;
    }
}
