<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;

interface ViewBuilder
{
    public function type(): ViewType;

    public function execute(ViewExecutionCriteria $criteria): ViewPage;
}
