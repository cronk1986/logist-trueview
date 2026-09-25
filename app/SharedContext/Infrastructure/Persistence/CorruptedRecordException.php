<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Persistence;

use RuntimeException;
use Throwable;

final class CorruptedRecordException extends RuntimeException
{
    public static function in(string $table, int $id, Throwable $reason): self
    {
        return new self(message: sprintf('Row %d of "%s" breaks a domain rule: %s', $id, $table, $reason->getMessage()), previous: $reason);
    }
}
