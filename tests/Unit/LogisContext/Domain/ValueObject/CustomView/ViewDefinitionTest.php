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
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra'],
                ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'retired']],
                ['column' => 'location', 'operator' => 'not_in', 'value' => [1, 2]],
                ['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-01']],
                ['column' => 'updated_at', 'operator' => 'in_last_days', 'value' => 0],
                ['column' => 'model', 'operator' => 'contains', 'value' => '%'],
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
            'unknown field' => [['column' => 'deleted_at', 'operator' => 'eq', 'value' => '2024-01-01'], '"deleted_at" is not a filterable field of my_assets views.'],
            'operator not allowed for text' => [['column' => 'model', 'operator' => 'gt', 'value' => 'A'], 'The operator "gt" cannot be used on "model".'],
            'contains on a choice' => [['column' => 'status', 'operator' => 'contains', 'value' => 'act'], 'The operator "contains" cannot be used on "status".'],
            'neq on an identifier' => [['column' => 'location', 'operator' => 'neq', 'value' => 1], 'The operator "neq" cannot be used on "location".'],
            'eq on a date' => [['column' => 'created_at', 'operator' => 'eq', 'value' => '2024-01-01'], 'The operator "eq" cannot be used on "created_at".'],
            'list for a single value' => [['column' => 'model', 'operator' => 'eq', 'value' => ['TC52']], 'The filter on "model" needs a single value.'],
            'null for a single value' => [['column' => 'model', 'operator' => 'eq', 'value' => null], 'The filter on "model" needs a single value.'],
            'empty text' => [['column' => 'model', 'operator' => 'eq', 'value' => ''], 'The filter on "model" needs text of 1 to 255 characters.'],
            'text too long' => [['column' => 'model', 'operator' => 'eq', 'value' => str_repeat(string: 'a', times: 256)], 'The filter on "model" needs text of 1 to 255 characters.'],
            'number for text' => [['column' => 'model', 'operator' => 'eq', 'value' => 52], 'The filter on "model" needs text of 1 to 255 characters.'],
            'unknown choice' => [['column' => 'status', 'operator' => 'eq', 'value' => 'lost'], 'The filter on "status" accepts only: active, inactive, retired.'],
            'string identifier' => [['column' => 'location', 'operator' => 'eq', 'value' => '1'], 'The filter on "location" needs positive integer ids.'],
            'zero identifier' => [['column' => 'id', 'operator' => 'in', 'value' => [1, 0]], 'The filter on "id" needs positive integer ids.'],
            'scalar for a list' => [['column' => 'id', 'operator' => 'in', 'value' => 1], 'The filter on "id" needs a list of 1 to 100 values.'],
            'empty list' => [['column' => 'id', 'operator' => 'in', 'value' => []], 'The filter on "id" needs a list of 1 to 100 values.'],
            'list too long' => [['column' => 'id', 'operator' => 'in', 'value' => range(start: 1, end: 101)], 'The filter on "id" needs a list of 1 to 100 values.'],
            'wrong date format' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '01/02/2024'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'impossible date' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-02-30'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'datetime instead of date' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-02-01 10:00:00'], 'The filter on "created_at" needs dates in the Y-m-d format.'],
            'range of one date' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01']], 'The filter on "created_at" needs a list of two dates.'],
            'range out of order' => [['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-02', '2024-01-01']], 'The first date of the filter on "created_at" cannot be after the second.'],
            'negative days' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => -1], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'too many days' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 366], 'The filter on "created_at" needs a number of days between 0 and 365.'],
            'days as text' => [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => '30'], 'The filter on "created_at" needs a number of days between 0 and 365.'],
        ];
    }

    public function test_the_list_limits_are_inclusive(): void
    {
        $definition = $this->definition(
            type: ViewType::MyAssets,
            columns: ['id'],
            filters: [
                ['column' => 'id', 'operator' => 'in', 'value' => range(start: 1, end: 100)],
                ['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 365],
                ['column' => 'model', 'operator' => 'eq', 'value' => str_repeat(string: 'a', times: 255)],
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
