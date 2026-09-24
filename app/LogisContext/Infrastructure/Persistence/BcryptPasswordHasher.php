<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence;

use Illuminate\Contracts\Hashing\Hasher;
use Logis\LogisContext\Domain\Model\User\PasswordHasher;

final class BcryptPasswordHasher implements PasswordHasher
{
    private const DUMMY_HASH = '$2y$12$QJVd05vodz3lcEAxRjJriep2kQb1DxTLXyetnD1.gCHC8m2M6/7a6';

    public function __construct(private readonly Hasher $hasher)
    {
    }

    public function verify(string $password, string $hash): bool
    {
        return $this->hasher->check(value: $password, hashedValue: $hash);
    }

    public function verifyAgainstDummyHash(string $password): void
    {
        $this->hasher->check(value: $password, hashedValue: self::DUMMY_HASH);
    }
}
