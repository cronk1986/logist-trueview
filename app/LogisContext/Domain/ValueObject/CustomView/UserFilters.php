<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class UserFilters
{
    /**
     * @param list<ViewFilter> $filters
     */
    private function __construct(private array $filters)
    {
    }

    public static function none(): self
    {
        return new self(filters: []);
    }

    /**
     * @param array<mixed> $filters
     */
    public static function fromList(array $filters): self
    {
        if (count(value: $filters) > ViewFilters::MAX_FILTERS) {
            throw InvalidViewFilterException::tooMany(max: ViewFilters::MAX_FILTERS);
        }

        $userFilters = [];

        foreach ($filters as $filter) {
            if (!is_array(value: $filter)) {
                throw InvalidViewFilterException::notAFilter();
            }

            $userFilters[] = ViewFilter::fromArray(filter: $filter);
        }

        return new self(filters: $userFilters);
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    /**
     * @return array<string, list<ViewFilter>>
     */
    public function byField(): array
    {
        $byField = [];

        foreach ($this->filters as $filter) {
            $byField[$filter->column()][] = $filter;
        }

        return $byField;
    }
}
