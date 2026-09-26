<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Services\Search;

use Logis\LogisContext\Application\Services\Search\SearchCriteria;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use Logis\LogisContext\Domain\ValueObject\Search\SearchTerm;

final class SearchCriteriaMother
{
    public static function create(string $term = 'zebra', int $limit = SearchLimit::DEFAULT, int $customerId = 1, bool $withSubAccounts = false): SearchCriteria
    {
        return new SearchCriteria(
            term: SearchTerm::fromString(value: $term),
            limit: SearchLimit::fromInt(value: $limit),
            customers: self::customers(customer: CustomerId::fromInt(value: $customerId), withSubAccounts: $withSubAccounts),
        );
    }

    private static function customers(CustomerId $customer, bool $withSubAccounts): VisibleCustomers
    {
        if ($withSubAccounts) {
            return VisibleCustomers::customerWithSubAccounts(customer: $customer);
        }

        return VisibleCustomers::ownCustomer(customer: $customer);
    }
}
