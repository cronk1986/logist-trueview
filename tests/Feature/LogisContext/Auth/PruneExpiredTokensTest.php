<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class PruneExpiredTokensTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_tokens_expired_for_longer_than_the_retention_are_pruned(): void
    {
        $this->freezeTime();
        $this->createUser();
        $expiredLongAgo = $this->logIn();
        $this->travel(value: 26)->hours();
        $active = $this->logIn();

        $this->assertSame(0, Artisan::call(command: 'sanctum:prune-expired', parameters: ['--hours' => 24]));

        $this->assertNull($this->storedToken(plainTextToken: $expiredLongAgo));
        $this->assertNotNull($this->storedToken(plainTextToken: $active));
    }

    public function test_the_cleanup_is_scheduled_hourly(): void
    {
        $events = collect(value: $this->app->make(abstract: Schedule::class)->events())
            ->filter(callback: fn ($event) => str_contains(haystack: (string) $event->command, needle: 'sanctum:prune-expired --hours=24'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()?->expression);
    }
}
