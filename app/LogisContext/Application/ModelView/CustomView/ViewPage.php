<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\CustomView;

/**
 * @phpstan-type Reference array{id: int, label: string|null}
 * @phpstan-type Row array<string, scalar|Reference|null>
 * @phpstan-type Shape array{rows: list<Row>, next_cursor: string|null}
 */
final readonly class ViewPage
{
    /**
     * @param list<Row> $rows
     */
    private function __construct(private array $rows, private ?string $nextCursor)
    {
    }

    public static function empty(): self
    {
        return new self(rows: [], nextCursor: null);
    }

    /**
     * @param list<Row> $rows
     */
    public static function from(array $rows, ?string $nextCursor): self
    {
        return new self(rows: $rows, nextCursor: $nextCursor);
    }

    /**
     * @param Shape $values
     */
    public static function fromArray(array $values): self
    {
        return new self(rows: $values['rows'], nextCursor: $values['next_cursor']);
    }

    /**
     * @return list<Row>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * @return Shape
     */
    public function toArray(): array
    {
        return [
            'rows' => $this->rows,
            'next_cursor' => $this->nextCursor,
        ];
    }
}
