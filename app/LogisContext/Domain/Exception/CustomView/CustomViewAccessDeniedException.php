<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\CustomView;

use Logis\SharedContext\Domain\Exception\ForbiddenException;

final class CustomViewAccessDeniedException extends ForbiddenException
{
    public static function manage(int $viewId): self
    {
        return new self(message: sprintf('Only the owner of view %d can change it.', $viewId));
    }

    public static function ownerWithoutCustomer(): self
    {
        return new self(message: 'A user without a customer cannot own views.');
    }
}
