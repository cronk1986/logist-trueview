<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewFilters
{
    public const MAX_FILTERS = 20;
    private const USER_FILTER = 'user_filter';

    /**
     * @param list<ViewFilter> $filters
     * @param array<string, UserFilterMode> $modes
     */
    private function __construct(private array $filters, private array $modes)
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
        $modes = [];

        foreach ($filters as $filter) {
            if (!is_array(value: $filter)) {
                throw InvalidViewFilterException::notAFilter();
            }

            $viewFilter = ViewFilter::fromArray(filter: $filter);
            $viewFilters[] = $viewFilter;
            $modes[$viewFilter->column()] = self::userFilterOf(filter: $filter, column: $viewFilter->column());
        }

        return new self(filters: $viewFilters, modes: $modes);
    }

    /**
     * @return list<ViewFilter>
     */
    public function all(): array
    {
        return $this->filters;
    }

    public function modeOf(string $field): ?UserFilterMode
    {
        return $this->modes[$field] ?? null;
    }

    /**
     * @return list<array{column: string, operator: string, value: scalar|null|list<scalar>, user_filter: string|null}>
     */
    public function toArray(): array
    {
        return array_map(
            callback: fn (ViewFilter $filter): array => [...$filter->toArray(), self::USER_FILTER => $this->modeOf(field: $filter->column())?->value],
            array: $this->filters,
        );
    }

    /**
     * @param array<mixed> $filter
     */
    private static function userFilterOf(array $filter, string $column): UserFilterMode
    {
        $value = $filter[self::USER_FILTER] ?? null;

        if (!is_string(value: $value)) {
            throw InvalidViewFilterException::missingUserFilter(column: $column);
        }

        $mode = UserFilterMode::tryFrom(value: $value);

        if ($mode === null) {
            throw InvalidViewFilterException::unknownUserFilter(column: $column, mode: $value);
        }

        return $mode;
    }
}
