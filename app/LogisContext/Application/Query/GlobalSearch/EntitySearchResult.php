<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\GlobalSearch;

/**
 * @phpstan-type Row array<string, scalar|null>
 * @phpstan-type Shape array{results: list<Row>, total: int}
 */
final readonly class EntitySearchResult
{
    /**
     * @param list<Row> $results
     */
    private function __construct(private array $results, private int $total)
    {
    }

    public static function empty(): self
    {
        return new self(results: [], total: 0);
    }

    /**
     * @param list<Row> $results
     */
    public static function from(array $results, int $total): self
    {
        return new self(results: $results, total: $total);
    }

    /**
     * @param Shape $values
     */
    public static function fromArray(array $values): self
    {
        return new self(results: $values['results'], total: $values['total']);
    }

    /**
     * @return list<Row>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * @return Shape
     */
    public function toArray(): array
    {
        return [
            'results' => $this->results,
            'total' => $this->total,
        ];
    }
}
