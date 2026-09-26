<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use Illuminate\Pagination\Cursor;

final class ViewCursor
{
    private const DIRECTION_KEY = '_pointsToNextItems';

    /**
     * @param list<string> $orderColumns
     */
    public static function decode(?string $encoded, array $orderColumns): ?Cursor
    {
        if ($encoded === null) {
            return null;
        }

        $parameters = self::parameters(encoded: $encoded);

        if (!self::hasExactly(parameters: $parameters, orderColumns: $orderColumns)) {
            throw InvalidViewCursorException::forAnotherSort();
        }

        return Cursor::fromEncoded(encodedString: $encoded);
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function parameters(string $encoded): array
    {
        $json = base64_decode(string: str_replace(search: ['-', '_'], replace: ['+', '/'], subject: $encoded), strict: true);
        $decoded = $json === false ? null : json_decode(json: $json, associative: true);

        if (!is_array(value: $decoded) || !is_bool(value: $decoded[self::DIRECTION_KEY] ?? null)) {
            throw InvalidViewCursorException::malformed();
        }

        unset($decoded[self::DIRECTION_KEY]);

        foreach ($decoded as $name => $value) {
            if (!is_string(value: $name) || ($value !== null && !is_scalar(value: $value))) {
                throw InvalidViewCursorException::malformed();
            }
        }

        return $decoded;
    }

    /**
     * @param array<string, scalar|null> $parameters
     * @param list<string> $orderColumns
     */
    private static function hasExactly(array $parameters, array $orderColumns): bool
    {
        $names = array_keys(array: $parameters);
        sort(array: $names);
        sort(array: $orderColumns);

        return $names === $orderColumns;
    }
}
