<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Event\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\AbstractDomainEvent;

final class CustomViewSetAsDefault extends AbstractDomainEvent
{
    public function __construct(
        private readonly CustomViewId $viewId,
        private readonly UserId $ownerId,
        private readonly ViewType $type,
    ) {
        parent::__construct();
    }

    public function viewId(): CustomViewId
    {
        return $this->viewId;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function type(): ViewType
    {
        return $this->type;
    }
}
