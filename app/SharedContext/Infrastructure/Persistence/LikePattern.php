<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Persistence;

final class LikePattern
{
    private const ESCAPED_CHARACTERS = '\\%_';

    public static function contains(string $value): string
    {
        return '%' . addcslashes(string: $value, characters: self::ESCAPED_CHARACTERS) . '%';
    }
}
