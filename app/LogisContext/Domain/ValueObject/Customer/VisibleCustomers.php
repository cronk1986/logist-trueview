<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\Customer;

final readonly class VisibleCustomers
{
    /**
     * @param list<int> $ids
     */
    private function __construct(private array $ids)
    {
    }

    public static function none(): self
    {
        return new self(ids: []);
    }

    /**
     * @param list<CustomerId> $ids
     */
    public static function fromIds(array $ids): self
    {
        $values = array_values(array: array_unique(array: array_map(
            callback: fn (CustomerId $id): int => $id->value(),
            array: $ids,
        )));
        sort(array: $values);

        return new self(ids: $values);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    /**
     * @return list<int>
     */
    public function values(): array
    {
        return $this->ids;
    }
}
