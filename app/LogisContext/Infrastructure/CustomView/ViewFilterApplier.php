<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\DateFilterRange;
use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldType;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilter;
use Logis\SharedContext\Infrastructure\Persistence\LikePattern;

final class ViewFilterApplier
{
    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     */
    public function apply(Builder $query, ViewFilter $filter, ViewField $field, string $column): void
    {
        if ($field->type() === ViewFieldType::Date) {
            $this->applyDateRange(query: $query, range: DateFilterRange::of(filter: $filter), column: $column);

            return;
        }

        $value = $filter->value();

        match ($filter->operator()) {
            FilterOperator::Equals => $query->where(column: $column, operator: '=', value: $value),
            FilterOperator::NotEquals => $query->where(column: fn (Builder $query) => $query->where(column: $column, operator: '!=', value: $value)->orWhereNull($column)),
            FilterOperator::In => $query->whereIn($column, $this->values(filter: $filter)),
            FilterOperator::NotIn => $query->where(column: fn (Builder $query) => $query->whereNotIn($column, $this->values(filter: $filter))->orWhereNull($column)),
            FilterOperator::Contains => $query->where(column: $column, operator: 'like', value: LikePattern::contains(value: $this->text(filter: $filter))),
            default => throw InvalidViewFilterException::operatorNotAllowed(column: $filter->column(), operator: $filter->operator()->value),
        };
    }

    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     */
    private function applyDateRange(Builder $query, DateFilterRange $range, string $column): void
    {
        $from = $range->from();
        $until = $range->until();

        if ($from !== null) {
            $query->where(column: $column, operator: '>=', value: $from);
        }

        if ($until !== null) {
            $query->where(column: $column, operator: '<', value: $until);
        }
    }

    private function text(ViewFilter $filter): string
    {
        $text = $filter->value();

        if (!is_string(value: $text)) {
            throw InvalidViewFilterException::invalidText(column: $filter->column(), maxLength: ViewField::MAX_TEXT_LENGTH);
        }

        return $text;
    }

    /**
     * @return list<scalar>
     */
    private function values(ViewFilter $filter): array
    {
        $values = $filter->value();

        if (!is_array(value: $values)) {
            throw InvalidViewFilterException::expectsList(column: $filter->column(), max: ViewField::MAX_LIST_SIZE);
        }

        return $values;
    }
}
