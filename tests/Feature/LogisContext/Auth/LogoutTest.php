<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LogoutTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_it_revokes_the_current_token(): void
    {
        $this->createUser();
        $token = $this->logIn();

        $this->postJson(uri: '/api/v1/auth/logout', data: [], headers: $this->bearer(token: $token))
            ->assertOk()
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'Logged out');

        $this->me(token: $token)->assertUnauthorized();
    }

    public function test_it_also_revokes_the_predecessor_in_its_grace_period(): void
    {
        $this->createUser();
        $token = $this->logIn();
        $newToken = (string) $this->me(token: $token)->headers->get(key: 'X-Refreshed-Token');

        $this->postJson(uri: '/api/v1/auth/logout', data: [], headers: $this->bearer(token: $newToken))->assertOk();

        $this->me(token: $newToken)->assertUnauthorized();
        $this->me(token: $token)->assertUnauthorized();
    }

    public function test_logging_out_with_the_old_token_revokes_its_successor(): void
    {
        $this->createUser();
        $token = $this->logIn();
        $newToken = (string) $this->me(token: $token)->headers->get(key: 'X-Refreshed-Token');

        $this->postJson(uri: '/api/v1/auth/logout', data: [], headers: $this->bearer(token: $token))->assertOk();

        $this->me(token: $newToken)->assertUnauthorized();
        $this->me(token: $token)->assertUnauthorized();
    }

    public function test_other_sessions_stay_active(): void
    {
        $this->createUser();
        $firstSession = $this->logIn();
        $secondSession = $this->logIn();

        $this->postJson(uri: '/api/v1/auth/logout', data: [], headers: $this->bearer(token: $firstSession))->assertOk();

        $this->me(token: $secondSession)->assertOk();
    }
}
