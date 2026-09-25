<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Persistence;

final class EagerLoad
{
    /**
     * @param list<string> $relations
     * @return list<string>
     */
    public static function nested(string $relation, array $relations): array
    {
        if ($relations === []) {
            return [$relation];
        }

        return array_map(callback: fn (string $nested): string => $relation . '.' . $nested, array: $relations);
    }
}
