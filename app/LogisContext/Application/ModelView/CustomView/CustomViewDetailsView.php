<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\User\User;

final readonly class CustomViewDetailsView
{
    /**
     * @param list<string> $columns
     * @param list<array{column: string, operator: string, value: scalar|null|list<scalar>, user_filter: string}> $filters
     */
    private function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $viewType,
        public array $columns,
        public array $filters,
        public bool $isDefault,
        public bool $isPublic,
        public bool $isOwner,
        public int $ownerId,
        public string $ownerName,
    ) {
    }

    public static function from(CustomView $view, User $viewer): self
    {
        $isOwner = $view->isOwnedBy(user: $viewer);

        return new self(
            id: $view->savedId()->value(),
            title: $view->title()->value(),
            description: $view->description(),
            viewType: $view->type()->value,
            columns: $view->columns()->toArray(),
            filters: $view->filters()->toArray(),
            isDefault: $isOwner && $view->isDefault(),
            isPublic: $view->isPublic(),
            isOwner: $isOwner,
            ownerId: $view->user()->id()->value(),
            ownerName: $view->user()->name(),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     description: ?string,
     *     view_type: string,
     *     columns: list<string>,
     *     filters: list<array{column: string, operator: string, value: scalar|null|list<scalar>, user_filter: string}>,
     *     is_default: bool,
     *     is_public: bool,
     *     is_owner: bool,
     *     owner: array{id: int, name: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'view_type' => $this->viewType,
            'columns' => $this->columns,
            'filters' => $this->filters,
            'is_default' => $this->isDefault,
            'is_public' => $this->isPublic,
            'is_owner' => $this->isOwner,
            'owner' => ['id' => $this->ownerId, 'name' => $this->ownerName],
        ];
    }
}
