<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\GlobalSearch;

use Logis\LogisContext\Application\Query\GlobalSearch\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;

final class SearchCriteriaMother
{
    /**
     * @param list<int> $customerIds
     */
    public static function create(string $term = 'zebra', int $limit = SearchLimit::DEFAULT, array $customerIds = [1]): SearchCriteria
    {
        return new SearchCriteria(
            term: SearchTerm::fromString(value: $term),
            limit: SearchLimit::fromInt(value: $limit),
            customers: VisibleCustomers::fromIds(ids: array_map(callback: fn (int $id): CustomerId => CustomerId::fromInt(value: $id), array: $customerIds)),
        );
    }
}
