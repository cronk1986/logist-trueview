<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\TimeoutException;

final class ViewExecutionTimedOutException extends TimeoutException
{
    public static function after(int $milliseconds): self
    {
        return new self(message: sprintf('The view took longer than %d ms to run. Add filters to narrow it down.', $milliseconds));
    }
}
