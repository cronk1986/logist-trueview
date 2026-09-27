<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

final readonly class CalendarDay
{
    private const FORMAT = 'Y-m-d';

    private function __construct(private CarbonImmutable $start)
    {
    }

    /**
     * The "!" in the format sets the time to midnight UTC. Parsing alone is not enough: it rolls impossible
     * dates over (2026-02-30 becomes 2026-03-02) and accepts unpadded ones (2026-9-7). Formatting the date
     * again and comparing it with the input rejects both.
     */
    public static function tryFrom(mixed $value): ?self
    {
        if (!is_string(value: $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat(format: '!' . self::FORMAT, time: $value, timezone: 'UTC');
        } catch (InvalidFormatException) {
            return null;
        }

        if (!$date || $date->format(format: self::FORMAT) !== $value) {
            return null;
        }

        return new self(start: $date);
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
