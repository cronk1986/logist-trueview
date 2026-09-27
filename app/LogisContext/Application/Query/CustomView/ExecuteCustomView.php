<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\CustomView;

final readonly class ExecuteCustomView
{
    /**
     * @param array<mixed> $filters
     */
    public function __construct(
        public int $userId,
        public int $viewId,
        public int $limit,
        public ?string $cursor,
        public ?string $sortField,
        public ?string $sortDirection,
        public ?string $term,
        public array $filters,
    ) {
    }
}
