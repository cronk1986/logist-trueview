<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\AccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\InvalidPlainTextTokenException;

final readonly class PlainTextToken
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (trim(string: $value) === '') {
            throw InvalidPlainTextTokenException::create();
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function hash(): TokenHash
    {
        return TokenHash::fromString(value: hash(algo: 'sha256', data: $this->value));
    }
}
