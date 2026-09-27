<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;
use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewDefinitionTest extends TestCase
{
    public function test_it_accepts_columns_and_filters_of_the_catalog(): void
    {
        $definition = $this->definition(
            type: ViewType::MyAssets,
            columns: ['serial_number', 'location'],
            filters: [
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked'],
                ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'retired'], 'user_filter' => 'locked'],
                ['column' => 'location', 'operator' => 'not_in', 'value' => [1, 2], 'user_filter' => 'locked'],
                ['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-01'], 'user_filter' => 'locked'],
                ['column' => 'updated_at', 'operator' => 'in_last_days', 'value' => 0, 'user_filter' => 'locked'],
                ['column' => 'model', 'operator' => 'contains', 'value' => '%', 'user_filter' => 'locked'],
            ],
        );

        $this->assertSame(ViewType::MyAssets, $definition->type());
        $this->assertSame(['serial_number', 'location'], $definition->columns()->toArray());
        $this->assertCount(6, $definition->filters()->all());
        $this->assertSame(ViewType::MyAssets, $definition->catalog()->type());
    }

    public function test_it_rejects_a_column_outside_the_catalog(): void
    {
        $this->expectException(InvalidViewColumnsException::class);
        $this->expectExceptionMessage('"customer_id" is not a column of my_assets views.');

        $this->definition(type: ViewType::MyAssets, columns: ['serial_number', 'customer_id'], filters: []);
    }

    public function test_a_column_of_another_view_type_is_rejected(): void
    {
        $this->expectException(InvalidViewColumnsException::class);

        $this->definition(type: ViewType::MyContracts, columns: ['serial_number'], filters: []);
    }

    /**
     * @param array<string, mixed> $filter
     */
    #[DataProvider('invalidFilters')]
    public function test_it_rejects_invalid_filters(array $filter, string $message): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage($message);

        $this->definition(type: ViewType::MyAssets, columns: ['id'], filters: [$filter]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'unknown field' => [['column' => 'deleted_at', 'operator' => 'eq', 'value' => '2024-01-01', 'user_filter' => 'locked'], '"deleted_at" is not a filterable field of my_assets views.'],
            'operator not allowed for text' => [['column' => 'model', 'operator' => 'gt', 'value' => 'A', 'user_filter' => 'locked'], 'The operator "gt" cannot be used on "model".'],
            'contains on a choice' => [['column' => 'status', 'operator' => 'contains', 'value' => 'act', 'user_filter' => 'locked'], 'The operator "contains" cannot be used on "status".'],
            'neq on an identifier' => [['column' => 'location', 'operator' => 'neq', 'value' => 1, 'user_filter' => 'locked'], 'The operator "neq" cannot be used on "location".'],
            'eq on a date' => [['column' => 'created_at', 'operator' => 'eq', 'value' => '2024-01-01', 'user_filter' => 'locked'], 'The operator "eq" cannot be used on "created_at".'],
            'list for a single value' => [['column' => 'model', 'operator' => 'eq', 'value' => ['TC52'], 'user_filter' => 'locked'], 'The filter on "model" needs a single value.'],
            'null for a single value' => [['column' => 'model', 'operator' => 'eq', 'value' => null, 'user_filter' => 'locked'], 'The filter on "model" needs a single value.'],
            'empty text' => [['column' => 'model', 'operator' => 'eq', 'value' => '', 'user_filter' => 'locked'], 'The filter on "model" needs text of 1 to 255 characters.'],
            'text too long' => [['column' => 'model', 'operator' => 'eq', 'value' => str_repeat(string: 'a', times: 256), 'user_filter' => 'locked'], 'The filter on "model" needs text of 1 to 255 characters.'],
            'number for text' => [['column' => 'model', 'operator' => 'eq', 'value' => 52, 'user_filter' => 'locked'], 'The filter on "model" needs text of 1 to 255 characters.'],
            'unknown choice' => [['column' => 'status', 'operator' => 'eq', 'value' => 'lost', 'user_filter' => 'locked'], 'The filter on "status" accepts only: active, inactive, retired.'],
            'string identifier' => [['column' => 'location', 'operator' => 'eq', 'value' => '1', 'user_filter' => 'locked'], 'The filter on "location" needs positive integer ids.'],
            'zero identifier' => [['column' => 'id', 'operator' => 'in', 'value' => [1, 0], 'user_filter' => 'locked'], 'The filter on "id" needs positive integer ids.'],
            'scalar for a list' => [['column' => 'id', 'operator' => 'in', 'value' => 1, 'user_filter' => 'locked'], 'The filter on "id" needs a list of 1 to 100 values.'],
            'empty list' => [['column' => 'id', 'operator' => 'in', 'value' => [], 'user_filter' => 'locked'], 'The filter on "id" needs a list of 1 to 100 values.'],
            'list too long' => [['column' => 'id', 'operator' => 'in', 'value' => range(start: 1, end: 101), 'user_filter' => 'locked'], 'The filter on "id" needs a list of 1 to 100 values.'],
            'wrong date format' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '01/02/2024', 'user_filter' => 'locked'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'impossible date' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-02-30', 'user_filter' => 'locked'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'datetime instead of date' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-02-01 10:00:00', 'user_filter' => 'locked'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'range of one date' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01'], 'user_filter' => 'locked'], 'The filter on "created_at" needs a list of two dates.'],
            'range out of order' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-02', '2024-01-01'], 'user_filter' => 'locked'], 'The first date of the filter on "created_at" cannot be after the second.'],
            'negative days' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => -1, 'user_filter' => 'locked'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'too many days' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 366, 'user_filter' => 'locked'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'blank text' => [['column' => 'model', 'operator' => 'eq', 'value' => '   ', 'user_filter' => 'locked'], 'The filter on "model" needs text of 1 to 255 characters.'],
            'boolean for text' => [['column' => 'model', 'operator' => 'eq', 'value' => true, 'user_filter' => 'locked'], 'The filter on "model" needs text of 1 to 255 characters.'],
            'number for a choice' => [['column' => 'status', 'operator' => 'eq', 'value' => 1, 'user_filter' => 'locked'], 'The filter on "status" accepts only: active, inactive, retired.'],
            'float identifier' => [['column' => 'id', 'operator' => 'eq', 'value' => 1.0, 'user_filter' => 'locked'], 'The filter on "id" needs positive integer ids.'],
            'fractional identifier' => [['column' => 'id', 'operator' => 'eq', 'value' => 1.5, 'user_filter' => 'locked'], 'The filter on "id" needs positive integer ids.'],
            'range of three dates' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-02', '2024-01-03'], 'user_filter' => 'locked'], 'The filter on "created_at" needs a list of two dates.'],
            'range with a non-date' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['x', '2024-01-01'], 'user_filter' => 'locked'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'days as a float' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30.0, 'user_filter' => 'locked'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'days as a boolean' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => true, 'user_filter' => 'locked'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'days as text' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => '30', 'user_filter' => 'locked'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    #[DataProvider('invalidUserFilterModes')]
    public function test_it_rejects_restricted_filters_that_are_not_a_single_discrete_filter(array $filters, string $message): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage($message);

        $this->definition(type: ViewType::MyAssets, columns: ['id'], filters: $filters);
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, string}>
     */
    public static function invalidUserFilterModes(): array
    {
        return [
            'restricted contains' => [[['column' => 'model', 'operator' => 'contains', 'value' => 'TC', 'user_filter' => 'restricted']], 'A restricted filter on "model" must use eq or in.'],
            'restricted neq' => [[['column' => 'status', 'operator' => 'neq', 'value' => 'retired', 'user_filter' => 'restricted']], 'A restricted filter on "status" must use eq or in.'],
            'restricted date' => [[['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30, 'user_filter' => 'restricted']], 'A restricted filter on "created_at" must use eq or in.'],
            'two restricted filters' => [
                [
                    ['column' => 'status', 'operator' => 'eq', 'value' => 'active', 'user_filter' => 'restricted'],
                    ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'restricted'],
                ],
                '"status" can have only one restricted filter.',
            ],
        ];
    }

    public function test_open_and_locked_fields_accept_ranges(): void
    {
        $definition = $this->definition(type: ViewType::MyAssets, columns: ['id'], filters: [
            ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'open'],
            ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'open'],
            ['column' => 'updated_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'locked'],
            ['column' => 'updated_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'locked'],
            ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'restricted'],
        ]);

        $this->assertCount(5, $definition->filters()->all());
    }

    public function test_the_list_limits_are_inclusive(): void
    {
        $definition = $this->definition(
            type: ViewType::MyAssets,
            columns: ['id'],
            filters: [
                ['column' => 'id', 'operator' => 'in', 'value' => range(start: 1, end: 100), 'user_filter' => 'locked'],
                ['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 365, 'user_filter' => 'locked'],
                ['column' => 'model', 'operator' => 'eq', 'value' => str_repeat(string: 'a', times: 255), 'user_filter' => 'locked'],
            ],
        );

        $this->assertCount(3, $definition->filters()->all());
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    private function definition(ViewType $type, array $columns, array $filters): ViewDefinition
    {
        return ViewDefinition::from(
            type: $type,
            columns: ViewColumns::fromList(columns: $columns),
            filters: ViewFilters::fromList(filters: $filters),
        );
    }
}
