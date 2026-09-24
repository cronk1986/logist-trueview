<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MeTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_it_returns_the_authenticated_user(): void
    {
        $user = $this->createUser();

        $this->me(token: $this->logIn())
            ->assertOk()
            ->assertJsonPath(path: 'message', expect: 'Authenticated user')
            ->assertJsonPath(path: 'data', expect: ['id' => $user->id, 'name' => 'Demo', 'email' => 'demo@logis.test']);
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson(uri: '/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath(path: 'message', expect: 'Unauthenticated');
    }

    public function test_a_request_without_json_accept_header_is_unauthenticated_too(): void
    {
        $this->get(uri: '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_token_expires_after_the_ttl_without_use(): void
    {
        $this->freezeTime();
        $this->createUser();
        $token = $this->logIn();

        $this->travel(value: 60)->minutes();
        $this->travel(value: 1)->seconds();

        $this->me(token: $token)->assertUnauthorized();
    }
}
