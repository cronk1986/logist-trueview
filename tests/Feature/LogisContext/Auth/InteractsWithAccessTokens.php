<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Auth;

use Illuminate\Testing\TestResponse;
use Logis\LogisContext\Infrastructure\Persistence\AccessTokenModel;
use Logis\LogisContext\Infrastructure\Persistence\UserModel;
use Symfony\Component\HttpFoundation\Response;

trait InteractsWithAccessTokens
{
    private function createUser(string $email = 'demo@logis.test', string $password = 'password'): UserModel
    {
        return UserModel::factory()->create(attributes: ['name' => 'Demo', 'email' => $email, 'password' => $password]);
    }

    private function logIn(string $email = 'demo@logis.test', string $password = 'password'): string
    {
        $response = $this->postJson(uri: '/api/v1/auth/login', data: ['email' => $email, 'password' => $password]);
        $response->assertOk();

        return (string) $response->json(key: 'data.token');
    }

    /** @return TestResponse<Response> */
    private function me(string $token): TestResponse
    {
        return $this->getJson(uri: '/api/v1/auth/me', headers: $this->bearer(token: $token));
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function storedToken(string $plainTextToken): ?AccessTokenModel
    {
        return AccessTokenModel::query()->where(column: 'token', operator: hash(algo: 'sha256', data: $plainTextToken))->first();
    }
}
