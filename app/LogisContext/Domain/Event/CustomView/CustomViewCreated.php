<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Event\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\Event\AbstractDomainEvent;

final class CustomViewCreated extends AbstractDomainEvent
{
    public function __construct(private readonly UserId $ownerId, private readonly CustomViewTitle $title)
    {
        parent::__construct();
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function title(): CustomViewTitle
    {
        return $this->title;
    }
}
