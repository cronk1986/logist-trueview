<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewSort;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;

final readonly class ViewExecutionCriteria
{
    public function __construct(
        public ViewDefinition $definition,
        public ViewSort $sort,
        public ViewPageSize $pageSize,
        public ?string $cursor,
        public ?SearchTerm $term,
        public VisibleCustomers $customers,
    ) {
    }
}
