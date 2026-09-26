<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidCustomViewTitleException;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomViewTitleTest extends TestCase
{
    public function test_titles_are_equal_whatever_their_case(): void
    {
        $this->assertTrue(CustomViewTitle::fromString(value: 'Zebra Scanners')->equals(other: CustomViewTitle::fromString(value: 'zebra scanners')));
        $this->assertFalse(CustomViewTitle::fromString(value: 'Zebra')->equals(other: CustomViewTitle::fromString(value: 'Honeywell')));
    }

    public function test_it_keeps_a_valid_value_without_surrounding_spaces(): void
    {
        $this->assertSame('Zebra scanners', CustomViewTitle::fromString(value: '  Zebra scanners ')->value());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_an_invalid_value(string $value): void
    {
        $this->expectException(InvalidCustomViewTitleException::class);

        CustomViewTitle::fromString(value: $value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'only spaces' => ['   '],
            'too long' => [str_repeat(string: 'a', times: 256)],
        ];
    }

    public function test_two_values_with_the_same_content_are_equal(): void
    {
        $this->assertTrue(CustomViewTitle::fromString(value: 'Zebra scanners')->equals(other: CustomViewTitle::fromString(value: 'Zebra scanners')));
        $this->assertFalse(CustomViewTitle::fromString(value: 'Zebra scanners')->equals(other: CustomViewTitle::fromString(value: 'other')));
    }
}
