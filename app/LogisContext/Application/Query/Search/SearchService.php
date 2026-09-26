<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\Search;

final readonly class SearchService
{
    /**
     * @param list<string> $entities
     */
    public function __construct(
        public int $userId,
        public string $term,
        public int $limit,
        public array $entities,
    ) {
    }
}
