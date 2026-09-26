<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Testing\TestResponse;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\LogisContext\Auth\InteractsWithAccessTokens;

trait InteractsWithCustomViews
{
    use InteractsWithAccessTokens;

    private function userOf(?CustomerModel $customer, string $email, bool $canViewSubAccounts = false): UserModel
    {
        return UserModel::factory()->create(attributes: [
            'customer_id' => $customer?->id,
            'can_view_sub_accounts' => $canViewSubAccounts,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function tokenOf(UserModel $user): string
    {
        return $this->logIn(email: $user->email);
    }

    /**
     * @param array<string, mixed> $data
     * @return TestResponse<Response>
     */
    private function callViews(string $method, string $token, string $path = '', array $data = []): TestResponse
    {
        return $this->json(method: $method, uri: '/api/v1/views' . $path, data: $data, headers: $this->bearer(token: $token));
    }
}
