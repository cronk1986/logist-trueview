<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidCustomViewIdException;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use PHPUnit\Framework\TestCase;

final class CustomViewIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, CustomViewId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidCustomViewIdException::class);

        CustomViewId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(CustomViewId::fromInt(value: 3)->equals(other: CustomViewId::fromInt(value: 3)));
        $this->assertFalse(CustomViewId::fromInt(value: 3)->equals(other: CustomViewId::fromInt(value: 4)));
    }
}
