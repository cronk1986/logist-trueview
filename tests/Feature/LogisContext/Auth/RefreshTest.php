<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RefreshTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_it_returns_a_new_token_and_keeps_the_old_one_during_the_grace_period(): void
    {
        $this->createUser();
        $token = $this->logIn();

        $response = $this->postJson(uri: '/api/v1/auth/refresh', data: [], headers: $this->bearer(token: $token));

        $response->assertOk()
            ->assertJsonPath(path: 'message', expect: 'Token refreshed')
            ->assertHeaderMissing(headerName: 'X-Refreshed-Token');
        $newToken = (string) $response->json(key: 'data.token');
        $this->assertNotSame($token, $newToken);
        $this->me(token: $newToken)->assertOk();
        $this->me(token: $token)->assertOk();
    }

    public function test_refreshing_an_already_rotated_token_is_a_conflict(): void
    {
        $this->createUser();
        $token = $this->logIn();
        $this->postJson(uri: '/api/v1/auth/refresh', data: [], headers: $this->bearer(token: $token))->assertOk();

        $this->postJson(uri: '/api/v1/auth/refresh', data: [], headers: $this->bearer(token: $token))
            ->assertConflict()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'The access token has already been rotated.');
    }
}
