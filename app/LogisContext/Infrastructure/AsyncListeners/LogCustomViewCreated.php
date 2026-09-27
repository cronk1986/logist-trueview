<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\AsyncListeners;

use Logis\LogisContext\Domain\Event\CustomView\CustomViewCreated;
use Logis\SharedContext\Infrastructure\Event\AsyncEventListener;
use Psr\Log\LoggerInterface;

final class LogCustomViewCreated extends AsyncEventListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(CustomViewCreated $event): void
    {
        $this->logger->info(message: 'Custom view created.', context: [
            'owner_id' => $event->ownerId()->value(),
            'title' => $event->title()->value(),
            'occurred_on' => $event->occurredOn()->toIso8601ZuluString(),
        ]);
    }
}
