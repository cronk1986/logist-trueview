<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;

/**
 * @phpstan-import-type Row from ViewPage
 */
final readonly class ViewResultView
{
    /**
     * @param list<string> $columns
     * @param list<Row> $rows
     */
    private function __construct(
        public int $id,
        public string $title,
        public string $viewType,
        public array $columns,
        public array $rows,
    ) {
    }

    public static function from(CustomView $view, ViewPage $page): self
    {
        return new self(
            id: $view->savedId()->value(),
            title: $view->title()->value(),
            viewType: $view->type()->value,
            columns: $view->columns()->toArray(),
            rows: $page->rows(),
        );
    }

    /**
     * @return array{view: array{id: int, title: string, view_type: string}, columns: list<string>, rows: list<Row>}
     */
    public function toArray(): array
    {
        return [
            'view' => ['id' => $this->id, 'title' => $this->title, 'view_type' => $this->viewType],
            'columns' => $this->columns,
            'rows' => $this->rows,
        ];
    }
}
