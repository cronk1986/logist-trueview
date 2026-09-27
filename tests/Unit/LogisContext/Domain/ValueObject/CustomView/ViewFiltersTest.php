<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilterMode;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewFiltersTest extends TestCase
{
    public function test_it_round_trips_the_json_shape(): void
    {
        $filters = [
            ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked'],
            ['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-01-01', 'user_filter' => 'locked'],
            ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'locked'],
            ['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30, 'user_filter' => 'locked'],
        ];

        $this->assertSame($filters, ViewFilters::fromList(filters: $filters)->toArray());
    }

    public function test_every_filter_keeps_the_mode_of_its_field(): void
    {
        $filters = ViewFilters::fromList(filters: [
            ['column' => 'status', 'operator' => 'in', 'value' => ['active'], 'user_filter' => 'restricted'],
            ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52', 'user_filter' => 'open'],
        ]);

        $this->assertSame(UserFilterMode::Restricted, $filters->modeOf(field: 'status'));
        $this->assertSame(UserFilterMode::Open, $filters->modeOf(field: 'model'));
        $this->assertNull($filters->modeOf(field: 'manufacturer'));
    }

    public function test_the_filters_of_one_field_share_their_mode(): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage('The filters on "created_at" must share the same user_filter mode.');

        ViewFilters::fromList(filters: [
            ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'open'],
            ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'locked'],
        ]);
    }

    public function test_it_lists_the_filters_of_a_field(): void
    {
        $filters = ViewFilters::fromList(filters: [
            ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'open'],
            ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52', 'user_filter' => 'open'],
            ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'open'],
        ]);

        $this->assertCount(2, $filters->of(field: 'created_at'));
        $this->assertSame([], $filters->of(field: 'status'));
    }

    public function test_a_filter_without_a_mode_is_rejected(): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage('The filter on "model" needs a user_filter mode: restricted, open or locked.');

        ViewFilters::fromList(filters: [['column' => 'model', 'operator' => 'eq', 'value' => 'TC52']]);
    }

    public function test_an_unknown_mode_is_rejected(): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage('The filter on "model" uses the unknown user_filter mode "public". Use restricted, open or locked.');

        ViewFilters::fromList(filters: [['column' => 'model', 'operator' => 'eq', 'value' => 'TC52', 'user_filter' => 'public']]);
    }

    public function test_a_view_may_have_no_filters(): void
    {
        $this->assertSame([], ViewFilters::fromList(filters: [])->all());
    }

    public function test_a_view_has_at_most_twenty_filters(): void
    {
        $filter = ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52', 'user_filter' => 'locked'];

        $this->assertCount(20, ViewFilters::fromList(filters: array_fill(start_index: 0, count: 20, value: $filter))->all());

        $this->expectException(InvalidViewFilterException::class);

        ViewFilters::fromList(filters: array_fill(start_index: 0, count: 21, value: $filter));
    }

    public function test_it_exposes_each_filter(): void
    {
        $filter = ViewFilters::fromList(filters: [['column' => ' model ', 'operator' => ' eq ', 'value' => 'TC52', 'user_filter' => 'locked']])->all()[0];

        $this->assertSame('model', $filter->column());
        $this->assertSame(FilterOperator::Equals, $filter->operator());
        $this->assertSame('TC52', $filter->value());
    }

    /**
     * @param array<mixed> $filters
     */
    #[DataProvider('invalidFilters')]
    public function test_it_rejects_invalid_filters(array $filters): void
    {
        $this->expectException(InvalidViewFilterException::class);

        ViewFilters::fromList(filters: $filters);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidFilters(): array
    {
        return [
            'not an object' => [['manufacturer']],
            'missing column' => [[['operator' => 'eq', 'value' => 'Zebra']]],
            'empty operator' => [[['column' => 'model', 'operator' => '', 'value' => 'TC52', 'user_filter' => 'locked']]],
            'unknown operator' => [[['column' => 'model', 'operator' => 'is_null', 'value' => null, 'user_filter' => 'locked']]],
            'missing value' => [[['column' => 'model', 'operator' => 'eq', 'user_filter' => 'locked']]],
            'nested array value' => [[['column' => 'model', 'operator' => 'in', 'value' => [['TC52']], 'user_filter' => 'locked']]],
            'associative array value' => [[['column' => 'model', 'operator' => 'in', 'value' => ['a' => 'TC52'], 'user_filter' => 'locked']]],
        ];
    }
}
