<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\Contract;

use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use PHPUnit\Framework\TestCase;

final class ContractStatusTest extends TestCase
{
    public function test_only_the_active_status_is_active(): void
    {
        $this->assertTrue(ContractStatus::Active->isActive());
        $this->assertFalse(ContractStatus::Inactive->isActive());
    }
}
