<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http;

use Illuminate\Support\Str;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;

final class PlainTextTokenGenerator
{
    private const ENTROPY_LENGTH = 40;

    public function __construct(private readonly string $prefix = '')
    {
    }

    public function generate(): PlainTextToken
    {
        $entropy = Str::random(length: self::ENTROPY_LENGTH);

        return PlainTextToken::fromString(value: $this->prefix . $entropy . hash(algo: 'crc32b', data: $entropy));
    }
}
