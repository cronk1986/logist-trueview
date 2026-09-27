<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\AsyncListeners;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Event\CustomView\CustomViewCreated;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\LogisContext\Infrastructure\AsyncListeners\LogCustomViewCreated;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class LogCustomViewCreatedTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
    }

    public function test_it_logs_the_owner_and_the_title_of_the_new_view(): void
    {
        CarbonImmutable::setTestNow(testNow: '2026-09-27 10:00:00');
        $logger = new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            /** @param array<mixed> $context */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        (new LogCustomViewCreated(logger: $logger))->handle(event: new CustomViewCreated(
            ownerId: UserId::fromInt(value: 7),
            title: CustomViewTitle::fromString(value: 'Zebra scanners'),
        ));

        $this->assertSame([[
            'level' => 'info',
            'message' => 'Custom view created.',
            'context' => ['owner_id' => 7, 'title' => 'Zebra scanners', 'occurred_on' => '2026-09-27T10:00:00Z'],
        ]], $logger->records);
    }
}
