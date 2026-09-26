<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewFilters
{
    public const MAX_FILTERS = 20;

    /**
     * @param list<ViewFilter> $filters
     */
    private function __construct(private array $filters)
    {
    }

    /**
     * @param array<mixed> $filters
     */
    public static function fromList(array $filters): self
    {
        if (count(value: $filters) > self::MAX_FILTERS) {
            throw InvalidViewFilterException::tooMany(max: self::MAX_FILTERS);
        }

        $viewFilters = [];

        foreach ($filters as $filter) {
            if (!is_array(value: $filter)) {
                throw InvalidViewFilterException::notAFilter();
            }

            $viewFilters[] = ViewFilter::fromArray(filter: $filter);
        }

        return new self(filters: $viewFilters);
    }

    /**
     * @return list<ViewFilter>
     */
    public function all(): array
    {
        return $this->filters;
    }

    /**
     * @return list<array{column: string, operator: string, value: scalar|null|list<scalar>}>
     */
    public function toArray(): array
    {
        return array_map(callback: fn (ViewFilter $filter): array => $filter->toArray(), array: $this->filters);
    }
}
