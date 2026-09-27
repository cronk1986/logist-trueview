<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\ModelView\CustomView\ViewResultView;
use Logis\LogisContext\Application\Services\CustomView\CustomViewAccess;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilders;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\SortDirection;
use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewSort;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;
use Logis\SharedContext\Domain\QueryResponse\Meta;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class ExecuteCustomViewHandler
{
    public function __construct(
        private readonly CustomViewAccess $access,
        private readonly ViewBuilders $builders,
    ) {
    }

    public function handle(ExecuteCustomView $query): QueryResponse
    {
        $user = $this->access->user(userId: $query->userId);
        $view = $this->access->viewVisibleTo(user: $user, viewId: $query->viewId);

        $criteria = new ViewExecutionCriteria(
            definition: $view->definition()->refinedBy(filters: UserFilters::fromList(filters: $query->filters)),
            sort: $this->sort(query: $query, view: $view),
            pageSize: ViewPageSize::fromInt(value: $query->limit),
            cursor: $query->cursor,
            term: $this->term(query: $query),
            customers: $user->visibleCustomers(),
        );

        $page = $this->execute(view: $view, criteria: $criteria);

        return QueryResponse::from(
            data: ViewResultView::from(view: $view, page: $page)->toArray(),
            meta: Meta::cursor(limit: $criteria->pageSize->value(), nextCursor: $page->nextCursor()),
        );
    }

    private function execute(CustomView $view, ViewExecutionCriteria $criteria): ViewPage
    {
        if ($criteria->customers->isEmpty()) {
            return ViewPage::empty();
        }

        return $this->builders->for(type: $view->type())->execute(criteria: $criteria);
    }

    private function sort(ExecuteCustomView $query, CustomView $view): ViewSort
    {
        if ($query->sortField === null) {
            return ViewSort::default();
        }

        return ViewSort::from(
            catalog: $view->definition()->catalog(),
            field: $query->sortField,
            direction: $query->sortDirection ?? SortDirection::Desc->value,
        );
    }

    private function term(ExecuteCustomView $query): ?SearchTerm
    {
        if ($query->term === null || trim(string: $query->term) === '') {
            return null;
        }

        return SearchTerm::fromString(value: $query->term);
    }
}
