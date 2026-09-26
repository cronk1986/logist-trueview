<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class DateFilterRange
{
    public const MAX_DAYS = 365;

    private function __construct(private ?CarbonImmutable $from, private ?CarbonImmutable $until)
    {
    }

    public static function of(ViewFilter $filter): self
    {
        return match ($filter->operator()) {
            FilterOperator::GreaterThan => new self(from: self::day(filter: $filter, value: $filter->value())->startOfNextDay(), until: null),
            FilterOperator::GreaterThanOrEqual => new self(from: self::day(filter: $filter, value: $filter->value())->start(), until: null),
            FilterOperator::LessThan => new self(from: null, until: self::day(filter: $filter, value: $filter->value())->start()),
            FilterOperator::LessThanOrEqual => new self(from: null, until: self::day(filter: $filter, value: $filter->value())->startOfNextDay()),
            FilterOperator::Between => self::between(filter: $filter),
            FilterOperator::InLastDays => self::inLastDays(filter: $filter),
            default => throw InvalidViewFilterException::operatorNotAllowed(column: $filter->column(), operator: $filter->operator()->value),
        };
    }

    public function from(): ?CarbonImmutable
    {
        return $this->from;
    }

    public function until(): ?CarbonImmutable
    {
        return $this->until;
    }

    private static function between(ViewFilter $filter): self
    {
        $bounds = $filter->value();

        if (!is_array(value: $bounds) || count(value: $bounds) !== 2) {
            throw InvalidViewFilterException::expectsRange(column: $filter->column());
        }

        $first = self::day(filter: $filter, value: $bounds[0]);
        $last = self::day(filter: $filter, value: $bounds[1]);

        if ($first->isAfter(other: $last)) {
            throw InvalidViewFilterException::rangeOutOfOrder(column: $filter->column());
        }

        return new self(from: $first->start(), until: $last->startOfNextDay());
    }

    private static function inLastDays(ViewFilter $filter): self
    {
        $days = $filter->value();

        if (!is_int(value: $days) || $days < 0 || $days > self::MAX_DAYS) {
            throw InvalidViewFilterException::expectsDays(column: $filter->column(), max: self::MAX_DAYS);
        }

        return new self(from: CalendarDay::today()->daysBefore(days: $days)->start(), until: null);
    }

    private static function day(ViewFilter $filter, mixed $value): CalendarDay
    {
        $day = CalendarDay::tryFrom(value: $value);

        if ($day === null) {
            throw InvalidViewFilterException::invalidDate(column: $filter->column());
        }

        return $day;
    }
}
