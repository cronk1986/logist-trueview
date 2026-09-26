<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;

final class DeleteCustomViewHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(DeleteCustomView $command): void
    {
        $owner = $this->access->user(userId: $command->userId);
        $view = $this->access->viewToChange(user: $owner, viewId: $command->viewId);

        $view->delete();
        $this->views->delete(id: $view->savedId());
    }
}
