<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\Search;

use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;

final readonly class SearchCriteria
{
    public function __construct(
        public SearchTerm $term,
        public SearchLimit $limit,
        public VisibleCustomers $customers,
    ) {
    }
}
