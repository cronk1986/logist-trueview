<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Event;

use Illuminate\Contracts\Queue\ShouldQueue;

abstract class AsyncEventListener implements ShouldQueue
{
    public string $queue = 'events';

    public int $tries = 3;

    public bool $afterCommit = false;
}
