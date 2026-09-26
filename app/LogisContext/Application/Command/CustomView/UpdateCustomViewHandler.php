<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\CustomView;

use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewTitleAlreadyUsedException;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class UpdateCustomViewHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(UpdateCustomView $command): void
    {
        $owner = $this->access->user(userId: $command->userId);
        $view = $this->access->viewToChange(user: $owner, viewId: $command->viewId);
        $title = CustomViewTitle::fromString(value: $command->title);

        if ($command->viewType !== null) {
            $view->ensureTypeIs(type: ViewType::fromName(name: $command->viewType));
        }

        $this->ensureTitleIsFree(view: $view, title: $title);

        $view->update(
            title: $title,
            description: $command->description,
            columns: ViewColumns::fromList(columns: $command->columns),
            filters: ViewFilters::fromList(filters: $command->filters),
            isPublic: $command->isPublic,
        );

        $this->views->save(view: $view);
    }

    private function ensureTitleIsFree(CustomView $view, CustomViewTitle $title): void
    {
        $viewWithTitle = $this->views->findByOwnerAndTitle(owner: $view->user()->id(), title: $title);

        if ($viewWithTitle !== null && !$viewWithTitle->savedId()->equals(other: $view->savedId())) {
            throw CustomViewTitleAlreadyUsedException::withTitle(title: $title->value());
        }
    }
}
