<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

enum ViewFieldType: string
{
    case Text = 'text';
    case Choice = 'choice';
    case Identifier = 'identifier';
    case Date = 'date';

    public function allows(FilterOperator $operator): bool
    {
        return in_array(needle: $operator, haystack: $this->operators(), strict: true);
    }

    /**
     * @return list<FilterOperator>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Text => [FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::In, FilterOperator::NotIn, FilterOperator::Contains],
            self::Choice => [FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::In, FilterOperator::NotIn],
            self::Identifier => [FilterOperator::Equals, FilterOperator::In, FilterOperator::NotIn],
            self::Date => [
                FilterOperator::GreaterThan,
                FilterOperator::GreaterThanOrEqual,
                FilterOperator::LessThan,
                FilterOperator::LessThanOrEqual,
                FilterOperator::Between,
                FilterOperator::InLastDays,
            ],
        };
    }
}
