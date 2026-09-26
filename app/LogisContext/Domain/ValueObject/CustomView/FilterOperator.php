<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

enum FilterOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case Between = 'between';
    case InLastDays = 'in_last_days';

    public function expectsList(): bool
    {
        return match ($this) {
            self::In, self::NotIn => true,
            default => false,
        };
    }

    public function expectsRange(): bool
    {
        return $this === self::Between;
    }

    public function expectsDays(): bool
    {
        return $this === self::InLastDays;
    }

    public function expectsSingleValue(): bool
    {
        return !$this->expectsList() && !$this->expectsRange() && !$this->expectsDays();
    }
}
