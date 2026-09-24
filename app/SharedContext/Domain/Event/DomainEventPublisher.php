<?php

declare(strict_types=1);

namespace Logis\SharedContext\Domain\Event;

final class DomainEventPublisher
{
    private static ?self $instance = null;

    private int $index = 0;

    /** @var array<int, list<DomainEvent>> */
    private array $events = [];

    /** @var array<class-string<DispatcherInterface>, DispatcherInterface> */
    private array $subscribers = [];

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function subscribe(DispatcherInterface $subscriber): void
    {
        $this->subscribers[$subscriber::class] ??= $subscriber;
    }

    public static function publish(DomainEvent $event): void
    {
        self::instance()->publishEvent(event: $event);
    }

    public function publishEvent(DomainEvent $event): void
    {
        $this->events[$this->index][] = $event;
    }

    /** @return array<int, list<DomainEvent>> */
    public function events(): array
    {
        return $this->events;
    }

    public function publishAllEvents(): void
    {
        $events = $this->eventsCurrentIndex();
        $this->index++;

        foreach ($this->subscribers as $subscriber) {
            foreach ($events as $event) {
                $subscriber->handle(event: $event);
            }
        }
    }

    public function hasEventsToPublish(): bool
    {
        return $this->eventsCurrentIndex() !== [];
    }

    public function clear(): void
    {
        $this->index = 0;
        $this->events = [];
    }

    /**
     * @template T of DomainEvent
     *
     * @param class-string<T> $domainEventType
     *
     * @return T|null
     */
    public static function lastEventOfType(string $domainEventType): ?DomainEvent
    {
        foreach (array_reverse(array: self::instance()->events) as $events) {
            foreach (array_reverse(array: $events) as $event) {
                if ($event instanceof $domainEventType) {
                    return $event;
                }
            }
        }

        return null;
    }

    /** @return list<DomainEvent> */
    private function eventsCurrentIndex(): array
    {
        return $this->events[$this->index] ?? [];
    }
}
