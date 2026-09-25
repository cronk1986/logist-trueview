<?php

declare(strict_types=1);

namespace Tests\Unit\SharedContext\Infrastructure\Persistence;

use Logis\SharedContext\Infrastructure\Persistence\LikePattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LikePatternTest extends TestCase
{
    #[DataProvider('values')]
    public function test_it_wraps_the_escaped_value_in_wildcards(string $value, string $expected): void
    {
        $this->assertSame($expected, LikePattern::contains(value: $value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function values(): iterable
    {
        yield 'plain text' => ['zebra', '%zebra%'];
        yield 'percent' => ['50%', '%50\%%'];
        yield 'underscore' => ['a_b', '%a\_b%'];
        yield 'backslash' => ['C:\temp', '%C:\\\\temp%'];
    }
}
