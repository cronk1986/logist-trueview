<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Asset;

use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use PHPUnit\Framework\TestCase;

final class AssetStatusTest extends TestCase
{
    public function test_only_the_active_status_is_active(): void
    {
        $this->assertTrue(AssetStatus::Active->isActive());
        $this->assertFalse(AssetStatus::Inactive->isActive());
        $this->assertFalse(AssetStatus::Retired->isActive());
    }
}
