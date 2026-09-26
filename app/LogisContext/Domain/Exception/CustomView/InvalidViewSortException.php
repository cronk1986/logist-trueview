<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class InvalidViewSortException extends InvalidValueException
{
    public static function notSortable(string $field, string $viewType): self
    {
        return new self(message: sprintf('%s views cannot be sorted by "%s".', $viewType, $field));
    }

    public static function unknownDirection(string $direction): self
    {
        return new self(message: sprintf('"%s" is not a sort direction. Use asc or desc.', $direction));
    }
}
