<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;
use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewDefinitionRefinementTest extends TestCase
{
    private ViewDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = ViewDefinition::from(
            type: ViewType::MyAssets,
            columns: ViewColumns::fromList(columns: ['serial_number', 'model', 'location']),
            filters: ViewFilters::fromList(filters: [
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked'],
                ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'restricted'],
                ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'open'],
                ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'open'],
            ]),
        );
    }

    public function test_without_user_filters_the_saved_filters_apply(): void
    {
        $this->assertSame($this->definition->filters()->toArray(), $this->definition->refinedBy(filters: UserFilters::none())->filters()->toArray());
    }

    public function test_a_restricted_field_can_be_narrowed_to_a_subset_of_its_values(): void
    {
        $refined = $this->refine(filters: [['column' => 'status', 'operator' => 'eq', 'value' => 'active']]);

        $this->assertSame([['column' => 'status', 'operator' => 'eq', 'value' => 'active']], $this->conditionsOf(definition: $refined, field: 'status'));
        $this->assertSame([['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']], $this->conditionsOf(definition: $refined, field: 'manufacturer'));
    }

    public function test_an_open_field_is_replaced_by_the_user_filters(): void
    {
        $refined = $this->refine(filters: [['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30]]);

        $this->assertSame([['column' => 'created_at', 'operator' => 'in_last_days', 'value' => 30]], $this->conditionsOf(definition: $refined, field: 'created_at'));
    }

    public function test_a_column_without_a_saved_filter_is_added(): void
    {
        $refined = $this->refine(filters: [['column' => 'location', 'operator' => 'in', 'value' => [3, 4]]]);

        $this->assertSame([['column' => 'location', 'operator' => 'in', 'value' => [3, 4]]], $this->conditionsOf(definition: $refined, field: 'location'));
        $this->assertCount(5, $refined->filters()->all());
    }

    public function test_a_hidden_field_with_an_open_or_restricted_filter_can_be_filtered(): void
    {
        $refined = $this->refine(filters: [
            ['column' => 'status', 'operator' => 'in', 'value' => ['inactive']],
            ['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-06-01'],
        ]);

        $this->assertCount(3, $refined->filters()->all());
    }

    public function test_the_saved_definition_is_not_changed(): void
    {
        $this->refine(filters: [['column' => 'location', 'operator' => 'eq', 'value' => 3]]);

        $this->assertCount(4, $this->definition->filters()->all());
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    #[DataProvider('rejectedFilters')]
    public function test_it_rejects_user_filters_the_view_does_not_allow(array $filters, string $message): void
    {
        $this->expectException(InvalidViewFilterException::class);
        $this->expectExceptionMessage($message);

        $this->refine(filters: $filters);
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, string}>
     */
    public static function rejectedFilters(): array
    {
        return [
            'locked field' => [[['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Honeywell']], 'The filter on "manufacturer" is locked by the owner of the view.'],
            'value outside the restricted ones' => [[['column' => 'status', 'operator' => 'eq', 'value' => 'retired']], 'The filter on "status" accepts only: active, inactive.'],
            'one value outside the restricted ones' => [[['column' => 'status', 'operator' => 'in', 'value' => ['active', 'retired']]], 'The filter on "status" accepts only: active, inactive.'],
            'restricted value in another case' => [[['column' => 'status', 'operator' => 'eq', 'value' => 'Active']], 'The filter on "status" accepts only: active, inactive, retired.'],
            'restricted identifier as text' => [[['column' => 'location', 'operator' => 'eq', 'value' => '3']], 'The filter on "location" needs positive integer ids.'],
            'restricted field with a non-discrete operator' => [[['column' => 'status', 'operator' => 'neq', 'value' => 'active']], 'A restricted filter on "status" must use eq or in.'],
            'hidden field without a saved filter' => [[['column' => 'item_name', 'operator' => 'eq', 'value' => 'Zebra TC52']], '"item_name" is not a column of this view, so it cannot be filtered.'],
            'unknown field' => [[['column' => 'customer_id', 'operator' => 'eq', 'value' => 1]], '"customer_id" is not a filterable field of my_assets views.'],
            'catalog rules still apply' => [[['column' => 'model', 'operator' => 'gt', 'value' => 'A']], 'The operator "gt" cannot be used on "model".'],
            'invalid value type' => [[['column' => 'location', 'operator' => 'eq', 'value' => 'Main']], 'The filter on "location" needs positive integer ids.'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    private function refine(array $filters): ViewDefinition
    {
        return $this->definition->refinedBy(filters: UserFilters::fromList(filters: $filters));
    }

    /**
     * @return list<array{column: string, operator: string, value: scalar|null|list<scalar>}>
     */
    private function conditionsOf(ViewDefinition $definition, string $field): array
    {
        $conditions = [];

        foreach ($definition->filters()->of(field: $field) as $filter) {
            $conditions[] = $filter->toArray();
        }

        return $conditions;
    }
}
