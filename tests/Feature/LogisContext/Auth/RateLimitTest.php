<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_a_guest_can_make_60_requests_per_minute(): void
    {
        for ($request = 1; $request <= 60; $request++) {
            $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'nobody@logis.test', 'password' => 'wrong'])
                ->assertUnauthorized();
        }

        $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'nobody@logis.test', 'password' => 'wrong'])
            ->assertTooManyRequests()
            ->assertHeader(headerName: 'Retry-After')
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'Too Many Requests');
    }

    public function test_an_authenticated_user_is_limited_by_user_and_not_by_ip(): void
    {
        $this->createUser();
        $token = $this->logIn();

        $this->me(token: $token)
            ->assertOk()
            ->assertHeader(headerName: 'X-RateLimit-Limit', value: '60')
            ->assertHeader(headerName: 'X-RateLimit-Remaining', value: '59');
    }
}
