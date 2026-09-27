<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\AsyncListeners;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Logis\LogisContext\Infrastructure\AsyncListeners\LogCustomViewCreated;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\Feature\LogisContext\CustomView\InteractsWithCustomViews;
use Tests\TestCase;

final class LogCustomViewCreatedTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(abstract: 'config')->set(key: 'queue.default', value: 'database');
        $this->user = $this->userOf(customer: CustomerModel::factory()->create(), email: 'owner@logis.test');
    }

    public function test_creating_a_view_queues_the_listener_on_the_events_queue(): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())->assertCreated();

        $this->assertSame(['events'], DB::table(table: 'jobs')->pluck(column: 'queue')->all());

        $payload = json_decode(json: (string) DB::table(table: 'jobs')->value(column: 'payload'), associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertSame(LogCustomViewCreated::class, $payload['displayName'] ?? null);
    }

    public function test_a_rejected_creation_queues_nothing(): void
    {
        CustomViewModel::factory()->create(attributes: ['user_id' => $this->user->id, 'title' => 'Zebra scanners']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())->assertConflict();

        $this->assertSame(0, DB::table(table: 'jobs')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Zebra scanners',
            'view_type' => 'my_assets',
            'columns' => ['serial_number'],
            'filters' => [],
        ];
    }
}
