<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\QueryResponse;

final class Meta
{
    /**
     * @param array<string, scalar|null> $values
     */
    private function __construct(private readonly array $values)
    {
    }

    /**
     * @param array<string, scalar|null> $values
     */
    public static function from(array $values): self
    {
        return new self(values: $values);
    }

    public static function pagination(int $page, int $limit, int $total): self
    {
        return new self(values: [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'has_more' => $page * $limit < $total,
        ]);
    }

    public static function cursor(int $limit, ?string $nextCursor): self
    {
        return new self(values: [
            'limit' => $limit,
            'next_cursor' => $nextCursor,
            'has_more' => $nextCursor !== null,
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
