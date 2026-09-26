<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFieldType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewFieldTypeTest extends TestCase
{
    /**
     * @param list<string> $allowed
     */
    #[DataProvider('allowedOperators')]
    public function test_each_type_allows_only_its_operators(ViewFieldType $type, array $allowed): void
    {
        foreach (FilterOperator::cases() as $operator) {
            $this->assertSame(
                in_array(needle: $operator->value, haystack: $allowed, strict: true),
                $type->allows(operator: $operator),
                sprintf('%s and %s', $type->value, $operator->value),
            );
        }
    }

    /**
     * @return array<string, array{ViewFieldType, list<string>}>
     */
    public static function allowedOperators(): array
    {
        return [
            'text' => [ViewFieldType::Text, ['eq', 'neq', 'in', 'not_in', 'contains']],
            'choice' => [ViewFieldType::Choice, ['eq', 'neq', 'in', 'not_in']],
            'identifier' => [ViewFieldType::Identifier, ['eq', 'in', 'not_in']],
            'date' => [ViewFieldType::Date, ['gt', 'gte', 'lt', 'lte', 'between', 'in_last_days']],
        ];
    }
}
