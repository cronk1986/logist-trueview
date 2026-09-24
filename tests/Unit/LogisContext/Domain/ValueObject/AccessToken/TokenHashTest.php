<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\AccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\InvalidTokenHashException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\TokenHash;
use PHPUnit\Framework\TestCase;

final class TokenHashTest extends TestCase
{
    public function test_it_accepts_a_sha256_hex_string(): void
    {
        $hash = hash(algo: 'sha256', data: 'token');

        $this->assertSame($hash, TokenHash::fromString(value: $hash)->value());
    }

    public function test_it_rejects_a_value_that_is_not_a_sha256_hex_string(): void
    {
        $this->expectException(InvalidTokenHashException::class);

        TokenHash::fromString(value: 'not-a-hash');
    }

    public function test_two_hashes_with_the_same_value_are_equal(): void
    {
        $hash = hash(algo: 'sha256', data: 'token');

        $this->assertTrue(TokenHash::fromString(value: $hash)->equals(other: TokenHash::fromString(value: $hash)));
        $this->assertFalse(TokenHash::fromString(value: $hash)->equals(other: TokenHash::fromString(value: hash(algo: 'sha256', data: 'other'))));
    }
}
