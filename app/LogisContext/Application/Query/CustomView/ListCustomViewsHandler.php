<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\CustomViewListItemView;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\SharedContext\Domain\QueryResponse\Meta;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class ListCustomViewsHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly CustomViewRepository $views,
    ) {
    }

    public function handle(ListCustomViews $query): QueryResponse
    {
        $user = $this->access->user(userId: $query->userId);
        $views = $this->visibleViews(user: $user, type: $this->viewType(query: $query));

        $items = array_map(
            callback: fn (CustomView $view): array => CustomViewListItemView::from(view: $view, viewer: $user)->toArray(),
            array: $views,
        );

        return QueryResponse::from(data: $items, meta: Meta::from(values: ['total' => count(value: $items)]));
    }

    /**
     * @return list<CustomView>
     */
    private function visibleViews(User $user, ?ViewType $type): array
    {
        $customer = $user->customer();

        if ($customer === null) {
            return [];
        }

        return $this->views->findOwnedByOrPublicIn(owner: $user->id(), customer: $customer->id(), type: $type);
    }

    private function viewType(ListCustomViews $query): ?ViewType
    {
        if ($query->viewType === null) {
            return null;
        }

        return ViewType::fromName(name: $query->viewType);
    }
}
