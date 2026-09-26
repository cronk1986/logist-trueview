<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Command\CustomView;

final readonly class UpdateCustomView
{
    /**
     * @param array<mixed> $columns
     * @param array<mixed> $filters
     */
    public function __construct(
        public int $userId,
        public int $viewId,
        public string $title,
        public ?string $description,
        public ?string $viewType,
        public array $columns,
        public array $filters,
        public bool $isPublic,
    ) {
    }
}
