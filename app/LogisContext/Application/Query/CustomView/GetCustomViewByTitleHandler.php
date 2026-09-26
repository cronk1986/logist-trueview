<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\CustomViewDetailsView;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class GetCustomViewByTitleHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(GetCustomViewByTitle $query): QueryResponse
    {
        $owner = $this->access->user(userId: $query->userId);
        $title = CustomViewTitle::fromString(value: $query->title);
        $view = $this->views->findByOwnerAndTitle(owner: $owner->id(), title: $title);

        if ($view === null) {
            throw CustomViewNotFoundException::withTitle(title: $title->value());
        }

        return QueryResponse::from(data: CustomViewDetailsView::from(view: $view, viewer: $owner)->toArray());
    }
}
