<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\AccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\InvalidTokenHashException;

final readonly class TokenHash
{
    private const SHA256_HEX = '/^[a-f0-9]{64}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match(pattern: self::SHA256_HEX, subject: $value) !== 1) {
            throw InvalidTokenHashException::create();
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return hash_equals(known_string: $this->value, user_string: $other->value);
    }
}
