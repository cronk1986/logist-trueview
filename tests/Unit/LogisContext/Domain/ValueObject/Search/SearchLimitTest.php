<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Search;

use Logis\LogisContext\Domain\Exception\Search\InvalidSearchLimitException;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchLimitTest extends TestCase
{
    #[DataProvider('validLimits')]
    public function test_it_accepts_a_limit_within_bounds(int $value): void
    {
        $this->assertSame($value, SearchLimit::fromInt(value: $value)->value());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validLimits(): iterable
    {
        yield 'minimum' => [1];
        yield 'default' => [SearchLimit::DEFAULT];
        yield 'maximum' => [50];
    }

    #[DataProvider('invalidLimits')]
    public function test_it_rejects_a_limit_out_of_bounds(int $value): void
    {
        $this->expectException(InvalidSearchLimitException::class);

        SearchLimit::fromInt(value: $value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above the maximum' => [51];
    }

    public function test_a_total_above_the_limit_exceeds_it(): void
    {
        $limit = SearchLimit::fromInt(value: 5);

        $this->assertTrue($limit->isExceededBy(total: 6));
        $this->assertFalse($limit->isExceededBy(total: 5));
        $this->assertFalse($limit->isExceededBy(total: 0));
    }
}
