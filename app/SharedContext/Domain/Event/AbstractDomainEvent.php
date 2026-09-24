<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\Event;

use Carbon\CarbonImmutable;

abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly CarbonImmutable $occurredOn;

    public function __construct()
    {
        $this->occurredOn = CarbonImmutable::now()->utc();
    }

    public function occurredOn(): CarbonImmutable
    {
        return $this->occurredOn;
    }
}
