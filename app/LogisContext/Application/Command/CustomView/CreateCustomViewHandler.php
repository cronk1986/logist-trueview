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

final class CreateCustomViewHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(CreateCustomView $command): void
    {
        $owner = $this->access->user(userId: $command->userId);
        $this->views->lockViewsOf(owner: $owner->id());
        $title = CustomViewTitle::fromString(value: $command->title);

        if ($this->views->findByOwnerAndTitle(owner: $owner->id(), title: $title) !== null) {
            throw CustomViewTitleAlreadyUsedException::withTitle(title: $title->value());
        }

        $view = CustomView::create(
            owner: $owner,
            title: $title,
            description: $command->description,
            type: ViewType::fromName(name: $command->viewType),
            columns: ViewColumns::fromList(columns: $command->columns),
            filters: ViewFilters::fromList(filters: $command->filters),
            isPublic: $command->isPublic,
        );

        $this->views->save(view: $view);
    }
}
