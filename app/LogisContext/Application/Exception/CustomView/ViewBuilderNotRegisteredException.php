<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Exception\CustomView;

use LogicException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class ViewBuilderNotRegisteredException extends LogicException
{
    public static function forType(ViewType $type): self
    {
        return new self(message: sprintf('No view builder is registered for "%s".', $type->value));
    }

    public static function registeredTwice(ViewType $type): self
    {
        return new self(message: sprintf('More than one view builder is registered for "%s".', $type->value));
    }
}
