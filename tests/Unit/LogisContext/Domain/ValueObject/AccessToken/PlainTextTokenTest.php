<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\AccessToken;

use Logis\LogisContext\Domain\Exception\AccessToken\InvalidPlainTextTokenException;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use PHPUnit\Framework\TestCase;

final class PlainTextTokenTest extends TestCase
{
    public function test_its_hash_is_the_sha256_of_the_token(): void
    {
        $plainTextToken = PlainTextToken::fromString(value: 'secret-token');

        $this->assertSame(hash(algo: 'sha256', data: 'secret-token'), $plainTextToken->hash()->value());
    }

    public function test_it_rejects_an_empty_token(): void
    {
        $this->expectException(InvalidPlainTextTokenException::class);

        PlainTextToken::fromString(value: '  ');
    }
}
