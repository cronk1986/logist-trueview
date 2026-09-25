<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Location;

use Logis\LogisContext\Domain\Exception\Location\InvalidLocationIdException;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;
use PHPUnit\Framework\TestCase;

final class LocationIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, LocationId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidLocationIdException::class);

        LocationId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(LocationId::fromInt(value: 3)->equals(other: LocationId::fromInt(value: 3)));
        $this->assertFalse(LocationId::fromInt(value: 3)->equals(other: LocationId::fromInt(value: 4)));
    }
}
