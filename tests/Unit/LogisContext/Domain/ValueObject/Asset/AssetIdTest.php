<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Asset;

use Logis\LogisContext\Domain\Exception\Asset\InvalidAssetIdException;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetId;
use PHPUnit\Framework\TestCase;

final class AssetIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, AssetId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidAssetIdException::class);

        AssetId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(AssetId::fromInt(value: 3)->equals(other: AssetId::fromInt(value: 3)));
        $this->assertFalse(AssetId::fromInt(value: 3)->equals(other: AssetId::fromInt(value: 4)));
    }
}
