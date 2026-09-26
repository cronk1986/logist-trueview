<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

final readonly class FieldMapping
{
    private function __construct(
        private string $column,
        private ?string $relation,
        private ?string $label,
    ) {
    }

    public static function toColumn(string $column): self
    {
        return new self(column: $column, relation: null, label: null);
    }

    public static function toRelation(string $relation, string $foreignKey, string $label): self
    {
        return new self(column: $foreignKey, relation: $relation, label: $label);
    }

    public function column(): string
    {
        return $this->column;
    }

    public function relation(): ?string
    {
        return $this->relation;
    }

    public function label(): ?string
    {
        return $this->label;
    }
}
