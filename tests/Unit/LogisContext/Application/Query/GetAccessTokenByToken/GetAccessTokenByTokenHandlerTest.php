<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\GetAccessTokenByToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Application\Query\GetAccessTokenByToken\GetAccessTokenByToken;
use Logis\LogisContext\Application\Query\GetAccessTokenByToken\GetAccessTokenByTokenHandler;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\AccessTokenMother;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\InMemoryAccessTokenRepository;

final class GetAccessTokenByTokenHandlerTest extends TestCase
{
    private InMemoryAccessTokenRepository $accessTokens;
    private GetAccessTokenByTokenHandler $handler;

    protected function setUp(): void
    {
        $this->accessTokens = new InMemoryAccessTokenRepository();
        $this->handler = new GetAccessTokenByTokenHandler(accessTokens: $this->accessTokens);
    }

    public function test_it_returns_the_access_token_view(): void
    {
        $expiresAt = CarbonImmutable::parse(time: '2026-09-25 11:00:00', timezone: 'UTC');
        $this->accessTokens->save(accessToken: AccessTokenMother::create(plainTextToken: 'secret-token', expiresAt: $expiresAt));

        $response = $this->handler->handle(query: new GetAccessTokenByToken(token: 'secret-token'));

        $this->assertSame(['token' => 'secret-token', 'expires_at' => '2026-09-25T11:00:00Z'], $response->data());
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->expectException(AccessTokenNotFoundException::class);

        $this->handler->handle(query: new GetAccessTokenByToken(token: 'unknown-token'));
    }
}
