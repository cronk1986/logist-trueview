<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_is_up(): void
    {
        $this->get(uri: '/up')->assertOk();
    }

    public function test_tests_run_against_the_testing_database(): void
    {
        $this->assertSame('logis_testing', DB::connection()->getDatabaseName());
    }
}
