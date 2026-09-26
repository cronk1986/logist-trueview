<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\CustomViewDetailsView;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class GetCustomViewHandler
{
    public function __construct(private readonly CustomViewAccess $access)
    {
    }

    public function handle(GetCustomView $query): QueryResponse
    {
        $user = $this->access->user(userId: $query->userId);
        $view = $this->access->viewVisibleTo(user: $user, viewId: $query->viewId);

        return QueryResponse::from(data: CustomViewDetailsView::from(view: $view, viewer: $user)->toArray());
    }
}
