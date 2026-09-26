<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewPageSizeException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ViewPageSizeTest extends TestCase
{
    public function test_it_accepts_the_bounds(): void
    {
        $this->assertSame(1, ViewPageSize::fromInt(value: 1)->value());
        $this->assertSame(1000, ViewPageSize::fromInt(value: 1000)->value());
        $this->assertSame(50, ViewPageSize::DEFAULT);
    }

    #[DataProvider('outOfRange')]
    public function test_it_rejects_values_out_of_range(int $value): void
    {
        $this->expectException(InvalidViewPageSizeException::class);

        ViewPageSize::fromInt(value: $value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfRange(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'over the maximum' => [1001]];
    }
}
