<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewColumnsException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewColumnsTest extends TestCase
{
    public function test_it_keeps_the_columns_in_order_without_surrounding_spaces(): void
    {
        $columns = ViewColumns::fromList(columns: ['serial_number', ' manufacturer ', 'location']);

        $this->assertSame(['serial_number', 'manufacturer', 'location'], $columns->toArray());
    }

    /**
     * @param array<mixed> $columns
     */
    #[DataProvider('invalidColumns')]
    public function test_it_rejects_invalid_columns(array $columns): void
    {
        $this->expectException(InvalidViewColumnsException::class);

        ViewColumns::fromList(columns: $columns);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidColumns(): array
    {
        return [
            'empty list' => [[]],
            'empty name' => [['serial_number', ' ']],
            'not a string' => [['serial_number', 3]],
            'duplicated' => [['model', 'model']],
            'duplicated after trimming' => [['model', ' model']],
        ];
    }
}
