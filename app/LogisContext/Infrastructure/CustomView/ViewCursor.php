<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use Illuminate\Pagination\Cursor;

/**
 * Laravel's cursor only stores the values of the order columns of the last row. The same cursor would be
 * accepted by a request with another sort field or direction, and would then skip or repeat rows.
 * encode() adds the sort it was built for (_sort, e.g. "created_at:desc"), and decode() rejects a cursor
 * whose sort or columns don't match the current request.
 */
final class ViewCursor
{
    private const DIRECTION_KEY = '_pointsToNextItems';
    private const SORT_KEY = '_sort';

    public static function encode(?Cursor $cursor, string $sort): ?string
    {
        if ($cursor === null) {
            return null;
        }

        $signed = new Cursor(
            parameters: [...$cursor->toArray(), self::SORT_KEY => $sort],
            pointsToNextItems: $cursor->pointsToNextItems(),
        );

        return $signed->encode();
    }

    /**
     * @param list<string> $orderColumns
     */
    public static function decode(?string $encoded, array $orderColumns, string $sort): ?Cursor
    {
        if ($encoded === null) {
            return null;
        }

        $values = self::values(encoded: $encoded);
        $pointsToNextItems = $values[self::DIRECTION_KEY];
        $signature = $values[self::SORT_KEY] ?? null;
        unset($values[self::DIRECTION_KEY], $values[self::SORT_KEY]);

        if ($signature !== $sort || !self::hasExactly(parameters: $values, orderColumns: $orderColumns)) {
            throw InvalidViewCursorException::forAnotherSort();
        }

        return new Cursor(parameters: $values, pointsToNextItems: $pointsToNextItems === true);
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function values(string $encoded): array
    {
        $json = base64_decode(string: str_replace(search: ['-', '_'], replace: ['+', '/'], subject: $encoded), strict: true);

        if ($json === false) {
            throw InvalidViewCursorException::malformed();
        }

        $decoded = json_decode(json: $json, associative: true);

        if (!is_array(value: $decoded) || ($decoded[self::DIRECTION_KEY] ?? null) !== true) {
            throw InvalidViewCursorException::malformed();
        }

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
