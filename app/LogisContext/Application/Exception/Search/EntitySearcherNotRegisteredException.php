<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Exception\Search;

use LogicException;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

final class EntitySearcherNotRegisteredException extends LogicException
{
    public static function forEntity(SearchableEntity $entity): self
    {
        return new self(message: sprintf('No searcher is registered for "%s".', $entity->value));
    }

    public static function registeredTwice(SearchableEntity $entity): self
    {
        return new self(message: sprintf('More than one searcher is registered for "%s".', $entity->value));
    }
}
