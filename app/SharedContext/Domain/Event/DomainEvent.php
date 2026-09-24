<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\Event;

use Carbon\CarbonImmutable;

interface DomainEvent
{
    public function occurredOn(): CarbonImmutable;
}
