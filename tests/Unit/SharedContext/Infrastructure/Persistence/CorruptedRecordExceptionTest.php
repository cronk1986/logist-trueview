<?php

declare(strict_types=1);

namespace Tests\Unit\SharedContext\Infrastructure\Persistence;

use DomainException;
use Logis\SharedContext\Infrastructure\Persistence\CorruptedRecordException;
use PHPUnit\Framework\TestCase;

final class CorruptedRecordExceptionTest extends TestCase
{
    public function test_it_names_the_row_and_keeps_the_broken_rule(): void
    {
        $reason = new DomainException(message: 'Asset 7 cannot use contract 3.');

        $exception = CorruptedRecordException::in(table: 'assets', id: 7, reason: $reason);

        $this->assertSame('Row 7 of "assets" breaks a domain rule: Asset 7 cannot use contract 3.', $exception->getMessage());
        $this->assertSame($reason, $exception->getPrevious());
    }
}
