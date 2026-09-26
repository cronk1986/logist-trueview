<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Event\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\SharedContext\Domain\Event\AbstractDomainEvent;

final class CustomViewUpdated extends AbstractDomainEvent
{
    public function __construct(private readonly CustomViewId $viewId)
    {
        parent::__construct();
    }

    public function viewId(): CustomViewId
    {
        return $this->viewId;
    }
}
