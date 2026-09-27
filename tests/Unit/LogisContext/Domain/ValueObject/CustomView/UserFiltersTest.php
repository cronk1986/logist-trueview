<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilters;
use PHPUnit\Framework\TestCase;

final class UserFiltersTest extends TestCase
{
    public function test_it_groups_the_filters_by_field(): void
    {
        $filters = UserFilters::fromList(filters: [
            ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01'],
            ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52'],
            ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31'],
        ]);

        $byField = $filters->byField();

        $this->assertSame(['created_at', 'model'], array_keys(array: $byField));
        $this->assertCount(2, $byField['created_at']);
    }

    public function test_the_fields_come_in_a_stable_order_whatever_the_order_sent(): void
    {
        $filters = UserFilters::fromList(filters: [
            ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52'],
            ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01'],
        ]);

        $this->assertSame(['created_at', 'model'], array_keys(array: $filters->byField()));
    }

    public function test_at_most_twenty_filters_are_accepted(): void
    {
        $this->expectException(InvalidViewFilterException::class);

        UserFilters::fromList(filters: array_fill(start_index: 0, count: 21, value: ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52']));
    }

    public function test_a_malformed_filter_is_rejected(): void
    {
        $this->expectException(InvalidViewFilterException::class);

        UserFilters::fromList(filters: ['model']);
    }
}
