<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\User;

use Logis\LogisContext\Domain\Exception\User\InvalidEmailException;

final readonly class Email
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $email = trim(string: $value);

        if (filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailException::create();
        }

        return new self(value: $email);
    }

    public function value(): string
    {
        return $this->value;
    }
}
