<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\GlobalSearch;

use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;

interface EntitySearcher
{
    public function entity(): SearchableEntity;

    public function search(SearchCriteria $criteria): EntitySearchResult;
}
