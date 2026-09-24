<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\AccessToken;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Application\ModelView\AccessToken\AccessTokenView;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\AccessTokenMother;

final class AccessTokenViewTest extends TestCase
{
    public function test_it_exposes_the_token_and_its_expiry_in_utc(): void
    {
        $accessToken = AccessTokenMother::create(plainTextToken: 'secret-token', expiresAt: CarbonImmutable::parse(time: '2026-09-25 13:00:00', timezone: 'Europe/Madrid'));

        $view = AccessTokenView::from(plainTextToken: PlainTextToken::fromString(value: 'secret-token'), accessToken: $accessToken);

        $this->assertSame(['token' => 'secret-token', 'expires_at' => '2026-09-25T11:00:00Z'], $view->toArray());
    }
}
