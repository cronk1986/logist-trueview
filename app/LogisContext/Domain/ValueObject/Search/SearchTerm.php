<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Search;

use Logis\LogisContext\Domain\Exception\Search\InvalidSearchTermException;

final readonly class SearchTerm
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim(string: $value);

        if ($trimmed === '') {
            throw InvalidSearchTermException::empty();
        }

        return new self(value: $trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
