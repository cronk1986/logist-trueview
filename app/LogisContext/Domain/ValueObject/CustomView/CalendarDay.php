<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CalendarDay
{
    private const FORMAT = 'Y-m-d';

    private function __construct(private CarbonImmutable $start)
    {
    }

    public static function tryFrom(mixed $value): ?self
    {
        if (!is_string(value: $value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(format: '!' . self::FORMAT, datetime: $value, timezone: new DateTimeZone(timezone: 'UTC'));

        if ($date === false || $date->format(format: self::FORMAT) !== $value) {
            return null;
        }

        return new self(start: CarbonImmutable::instance(date: $date));
    }

    public static function today(): self
    {
        return new self(start: CarbonImmutable::now()->utc()->startOfDay());
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function startOfNextDay(): CarbonImmutable
    {
        return $this->start->addDay();
    }

    public function daysBefore(int $days): self
    {
        return new self(start: $this->start->subDays($days));
    }

    public function isAfter(self $other): bool
    {
        return $this->start->greaterThan(date: $other->start);
    }
}
