<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

final readonly class ViewField
{
    /**
     * @param list<string> $choices
     */
    private function __construct(
        private string $name,
        private ViewFieldType $type,
        private array $choices,
        private bool $sortable,
    ) {
    }

    public static function text(string $name, bool $sortable): self
    {
        return new self(name: $name, type: ViewFieldType::Text, choices: [], sortable: $sortable);
    }

    /**
     * @param list<string> $choices
     */
    public static function choice(string $name, array $choices): self
    {
        return new self(name: $name, type: ViewFieldType::Choice, choices: $choices, sortable: true);
    }

    public static function identifier(string $name, bool $sortable): self
    {
        return new self(name: $name, type: ViewFieldType::Identifier, choices: [], sortable: $sortable);
    }

    public static function date(string $name): self
    {
        return new self(name: $name, type: ViewFieldType::Date, choices: [], sortable: true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): ViewFieldType
    {
        return $this->type;
    }

    /**
     * @return list<string>
     */
    public function choices(): array
    {
        return $this->choices;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function allows(FilterOperator $operator): bool
    {
        return $this->type->allows(operator: $operator);
    }
}
