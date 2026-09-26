<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;

final class SetDefaultCustomViewHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(SetDefaultCustomView $command): void
    {
        $owner = $this->access->user(userId: $command->userId);
        $view = $this->access->viewToChange(user: $owner, viewId: $command->viewId);
        $currentDefault = $this->views->findDefaultOf(owner: $owner->id(), type: $view->type());

        if ($currentDefault !== null && $currentDefault->savedId()->equals(other: $view->savedId())) {
            return;
        }

        $this->unmark(view: $currentDefault);

        $view->markAsDefault();
        $this->views->save(view: $view);
    }

    private function unmark(?CustomView $view): void
    {
        if ($view === null) {
            return;
        }

        $view->unmarkAsDefault();
        $this->views->save(view: $view);
    }
}
