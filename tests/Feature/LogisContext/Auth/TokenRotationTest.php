<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Logis\LogisContext\Infrastructure\Http\Middleware\RotateAccessTokenMiddleware;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\AccessTokenModel;
use Logis\SharedContext\Infrastructure\Http\ApiResponse;
use Tests\TestCase;

final class TokenRotationTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_an_authenticated_request_returns_a_new_token_that_works(): void
    {
        $this->createUser();
        $token = $this->logIn();

        $newToken = (string) $this->me(token: $token)->assertOk()->headers->get(key: 'X-Refreshed-Token');

        $this->assertNotSame('', $newToken);
        $this->assertNotSame($token, $newToken);
        $this->me(token: $newToken)->assertOk();
    }

    public function test_the_successor_is_linked_to_its_predecessor(): void
    {
        $this->createUser();
        $token = $this->logIn();

        $newToken = (string) $this->me(token: $token)->headers->get(key: 'X-Refreshed-Token');

        $this->assertSame(hash(algo: 'sha256', data: $token), $this->storedToken(plainTextToken: $newToken)?->rotated_from);
        $this->assertNotNull($this->storedToken(plainTextToken: $token)?->rotated_at);
    }

    public function test_the_old_token_works_during_the_grace_period_and_not_after(): void
    {
        $this->freezeTime();
        $this->createUser();
        $token = $this->logIn();
        $this->me(token: $token)->assertOk();

        $this->travel(value: 9)->seconds();
        $this->me(token: $token)->assertOk();

        $this->travel(value: 2)->seconds();
        $this->me(token: $token)->assertUnauthorized();
    }

    public function test_a_token_revoked_by_a_concurrent_logout_is_not_rotated_and_the_response_is_kept(): void
    {
        Route::middleware(['api', 'auth:sanctum', RotateAccessTokenMiddleware::class])
            ->get('/api/v1/test/revoked-during-request', function () {
                AccessTokenModel::query()->delete();

                return ApiResponse::success(message: 'Done');
            });
        $this->createUser();
        $token = $this->logIn();

        $this->getJson(uri: '/api/v1/test/revoked-during-request', headers: $this->bearer(token: $token))
            ->assertOk()
            ->assertHeaderMissing(headerName: 'X-Refreshed-Token');
    }

    public function test_a_concurrent_request_with_the_old_token_is_not_rotated_again(): void
    {
        $this->createUser();
        $token = $this->logIn();
        $this->me(token: $token)->assertHeader(headerName: 'X-Refreshed-Token');

        $this->me(token: $token)->assertOk()->assertHeaderMissing(headerName: 'X-Refreshed-Token');

        $this->assertDatabaseCount(table: 'personal_access_tokens', count: 2);
    }
}
