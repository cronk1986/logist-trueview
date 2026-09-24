<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Event\User;

use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\AbstractDomainEvent;

final class UserLoggedIn extends AbstractDomainEvent
{
    public function __construct(private readonly UserId $userId)
    {
        parent::__construct();
    }

    public function userId(): UserId
    {
        return $this->userId;
    }
}
