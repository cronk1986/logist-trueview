<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_and_store_its_metadata(): void
    {
        $this->freezeTime();
        $user = $this->createUser();

        $response = $this->postJson(
            uri: '/api/v1/auth/login',
            data: ['email' => 'demo@logis.test', 'password' => 'password'],
            headers: ['User-Agent' => 'LoginTest'],
        );

        $expiresAt = CarbonImmutable::now()->utc()->addMinutes(60)->toIso8601ZuluString();
        $response->assertOk()
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'Logged in')
            ->assertJsonPath(path: 'data.expires_at', expect: $expiresAt);
        $this->assertSame(['token', 'expires_at'], array_keys(array: (array) $response->json(key: 'data')));

        $storedToken = $this->storedToken(plainTextToken: (string) $response->json(key: 'data.token'));
        $this->assertNotNull($storedToken);
        $this->assertSame($user->id, $storedToken->tokenable_id);
        $this->assertSame('user', $storedToken->tokenable_type);
        $this->assertSame('127.0.0.1', $storedToken->ip_address);
        $this->assertSame('LoginTest', $storedToken->user_agent);
        $this->assertSame($expiresAt, $storedToken->expires_at->toIso8601ZuluString());
    }

    public function test_the_returned_token_authenticates_requests(): void
    {
        $this->createUser();

        $this->me(token: $this->logIn())->assertOk();
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_response(): void
    {
        $this->createUser();

        $wrongPassword = $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'demo@logis.test', 'password' => 'wrong']);
        $unknownEmail = $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'nobody@logis.test', 'password' => 'password']);

        $wrongPassword->assertUnauthorized()->assertJsonPath(path: 'message', expect: 'Invalid credentials');
        $unknownEmail->assertUnauthorized()->assertJsonPath(path: 'message', expect: 'Invalid credentials');
        $this->assertDatabaseCount(table: 'personal_access_tokens', count: 0);
    }

    public function test_invalid_input_returns_the_field_errors_in_data(): void
    {
        $this->postJson(uri: '/api/v1/auth/login', data: ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'Validation failed')
            ->assertJsonPath(path: 'data.email.0', expect: 'The email field must be a valid email address.')
            ->assertJsonPath(path: 'data.password.0', expect: 'The password field is required.');
    }
}
