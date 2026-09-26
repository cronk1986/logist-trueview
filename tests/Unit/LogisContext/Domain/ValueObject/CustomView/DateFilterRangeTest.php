<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\DateFilterRange;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateFilterRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('ranges')]
    public function test_each_operator_becomes_a_half_open_range_of_whole_utc_days(string $operator, mixed $value, ?string $from, ?string $until): void
    {
        $range = DateFilterRange::of(filter: ViewFilter::fromArray(filter: ['column' => 'created_at', 'operator' => $operator, 'value' => $value]));

        $this->assertSame($from, $range->from()?->toDateTimeString());
        $this->assertSame($until, $range->until()?->toDateTimeString());
    }

    /**
     * @return array<string, array{string, mixed, ?string, ?string}>
     */
    public static function ranges(): array
    {
        return [
            'gt starts the next day' => ['gt', '2024-01-01', '2024-01-02 00:00:00', null],
            'gte starts that day' => ['gte', '2024-01-01', '2024-01-01 00:00:00', null],
            'lt ends before that day' => ['lt', '2024-01-01', null, '2024-01-01 00:00:00'],
            'lte ends after that day' => ['lte', '2024-01-01', null, '2024-01-02 00:00:00'],
            'between includes both days' => ['between', ['2024-01-01', '2024-01-31'], '2024-01-01 00:00:00', '2024-02-01 00:00:00'],
            'end of month' => ['lte', '2024-02-29', null, '2024-03-01 00:00:00'],
        ];
    }

    public function test_in_last_days_counts_back_from_the_start_of_today_in_utc(): void
    {
        CarbonImmutable::setTestNow(testNow: CarbonImmutable::parse(time: '2024-03-10 23:59:59', timezone: 'UTC'));

        $today = DateFilterRange::of(filter: ViewFilter::fromArray(filter: ['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 0]));
        $lastMonth = DateFilterRange::of(filter: ViewFilter::fromArray(filter: ['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30]));

        $this->assertSame('2024-03-10 00:00:00', $today->from()?->toDateTimeString());
        $this->assertSame('2024-02-09 00:00:00', $lastMonth->from()?->toDateTimeString());
        $this->assertNull($today->until());
    }

    public function test_the_boundaries_are_in_utc(): void
    {
        $range = DateFilterRange::of(filter: ViewFilter::fromArray(filter: ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01']));

        $this->assertSame('UTC', $range->from()?->getTimezone()->getName());
    }

    public function test_a_non_date_operator_has_no_range(): void
    {
        $this->expectException(InvalidViewFilterException::class);

        DateFilterRange::of(filter: ViewFilter::fromArray(filter: ['column' => 'created_at', 'operator' => 'eq', 'value' => '2024-01-01']));
    }
}
