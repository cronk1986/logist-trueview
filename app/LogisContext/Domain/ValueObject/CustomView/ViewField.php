<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewFilterException;

final readonly class ViewField
{
    public const MAX_LIST_SIZE = 100;
    private const MAX_TEXT_LENGTH = 255;

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

    public function ensureAccepts(ViewFilter $filter): void
    {
        $operator = $filter->operator();

        if (!$this->type->allows(operator: $operator)) {
            throw InvalidViewFilterException::operatorNotAllowed(column: $this->name, operator: $operator->value);
        }

        if ($this->type === ViewFieldType::Date) {
            DateFilterRange::of(filter: $filter);

            return;
        }

        if ($operator->expectsList()) {
            $this->ensureList(value: $filter->value());

            return;
        }

        $this->ensureSingleValue(value: $filter->value());
    }

    private function ensureList(mixed $value): void
    {
        if (!is_array(value: $value) || $value === [] || count(value: $value) > self::MAX_LIST_SIZE) {
            throw InvalidViewFilterException::expectsList(column: $this->name, max: self::MAX_LIST_SIZE);
        }

        foreach ($value as $item) {
            $this->ensureValidValue(value: $item);
        }
    }

    private function ensureSingleValue(mixed $value): void
    {
        if ($value === null || is_array(value: $value)) {
            throw InvalidViewFilterException::expectsSingleValue(column: $this->name);
        }

        $this->ensureValidValue(value: $value);
    }

    private function ensureValidValue(mixed $value): void
    {
        if ($this->type === ViewFieldType::Choice) {
            $this->ensureChoice(value: $value);

            return;
        }

        if ($this->type === ViewFieldType::Identifier) {
            $this->ensureIdentifier(value: $value);

            return;
        }

        $this->ensureText(value: $value);
    }

    private function ensureText(mixed $value): void
    {
        if (!is_string(value: $value) || trim(string: $value) === '' || mb_strlen(string: $value) > self::MAX_TEXT_LENGTH) {
            throw InvalidViewFilterException::invalidText(column: $this->name, maxLength: self::MAX_TEXT_LENGTH);
        }
    }

    private function ensureChoice(mixed $value): void
    {
        if (!in_array(needle: $value, haystack: $this->choices, strict: true)) {
            throw InvalidViewFilterException::invalidChoice(column: $this->name, choices: $this->choices);
        }
    }

    private function ensureIdentifier(mixed $value): void
    {
        if (!is_int(value: $value) || $value < 1) {
            throw InvalidViewFilterException::invalidIdentifier(column: $this->name);
        }
    }
}
