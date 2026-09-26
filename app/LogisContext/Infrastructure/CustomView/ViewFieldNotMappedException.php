<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use LogicException;

final class ViewFieldNotMappedException extends LogicException
{
    public static function field(string $field, string $viewType): self
    {
        return new self(message: sprintf('The field "%s" of %s views has no column.', $field, $viewType));
    }
}
