<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewFiltersTest extends TestCase
{
    public function test_it_round_trips_the_json_shape(): void
    {
        $filters = [
            ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra'],
            ['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-01-01'],
            ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive']],
            ['column' => 'contract_id', 'operator' => 'is_null', 'value' => null],
        ];

        $this->assertSame($filters, ViewFilters::fromList(filters: $filters)->toArray());
    }

    public function test_a_view_may_have_no_filters(): void
    {
        $this->assertSame([], ViewFilters::fromList(filters: [])->all());
    }

    public function test_it_exposes_each_filter(): void
    {
        $filter = ViewFilters::fromList(filters: [['column' => ' model ', 'operator' => ' eq ', 'value' => 'TC52']])->all()[0];

        $this->assertSame('model', $filter->column());
        $this->assertSame('eq', $filter->operator());
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
            'empty operator' => [[['column' => 'model', 'operator' => '', 'value' => 'TC52']]],
            'missing value' => [[['column' => 'model', 'operator' => 'eq']]],
            'nested array value' => [[['column' => 'model', 'operator' => 'in', 'value' => [['TC52']]]]],
            'associative array value' => [[['column' => 'model', 'operator' => 'in', 'value' => ['a' => 'TC52']]]],
        ];
    }
}
