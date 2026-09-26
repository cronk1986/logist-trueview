<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\User\User;

final readonly class CustomViewListItemView
{
    private function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $viewType,
        public bool $isDefault,
        public bool $isPublic,
        public bool $isOwner,
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
            isDefault: $isOwner && $view->isDefault(),
            isPublic: $view->isPublic(),
            isOwner: $isOwner,
        );
    }

    /**
     * @return array{id: int, title: string, description: ?string, view_type: string, is_default: bool, is_public: bool, is_owner: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'view_type' => $this->viewType,
            'is_default' => $this->isDefault,
            'is_public' => $this->isPublic,
            'is_owner' => $this->isOwner,
        ];
    }
}
