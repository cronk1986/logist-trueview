<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use PHPUnit\Framework\TestCase;

final class FilterOperatorTest extends TestCase
{
    public function test_only_in_and_not_in_expect_a_list(): void
    {
        $this->assertSame([FilterOperator::In, FilterOperator::NotIn], $this->operatorsWhere(condition: fn (FilterOperator $operator): bool => $operator->expectsList()));
    }

    public function test_only_between_expects_a_range(): void
    {
        $this->assertSame([FilterOperator::Between], $this->operatorsWhere(condition: fn (FilterOperator $operator): bool => $operator->expectsRange()));
    }

    public function test_only_in_last_days_expects_days(): void
    {
        $this->assertSame([FilterOperator::InLastDays], $this->operatorsWhere(condition: fn (FilterOperator $operator): bool => $operator->expectsDays()));
    }

    public function test_the_other_operators_expect_a_single_value(): void
    {
        $this->assertSame(
            [
                FilterOperator::Equals,
                FilterOperator::NotEquals,
                FilterOperator::Contains,
                FilterOperator::GreaterThan,
                FilterOperator::GreaterThanOrEqual,
                FilterOperator::LessThan,
                FilterOperator::LessThanOrEqual,
            ],
            $this->operatorsWhere(condition: fn (FilterOperator $operator): bool => $operator->expectsSingleValue()),
        );
    }

    /**
     * @param callable(FilterOperator): bool $condition
     * @return list<FilterOperator>
     */
    private function operatorsWhere(callable $condition): array
    {
        return array_values(array: array_filter(array: FilterOperator::cases(), callback: $condition));
    }
}
