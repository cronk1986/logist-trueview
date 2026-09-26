<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

final class InMemoryViewBuilder implements ViewBuilder
{
    /** @var list<ViewExecutionCriteria> */
    private array $executions = [];

    public function __construct(private readonly ViewType $type, private readonly ViewPage $page)
    {
    }

    public function type(): ViewType
    {
        return $this->type;
    }

    public function execute(ViewExecutionCriteria $criteria): ViewPage
    {
        $this->executions[] = $criteria;

        return $this->page;
    }

    /**
     * @return list<ViewExecutionCriteria>
     */
    public function executions(): array
    {
        return $this->executions;
    }
}
