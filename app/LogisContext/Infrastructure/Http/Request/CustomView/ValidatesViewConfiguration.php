<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Validation\Rule;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use Logis\LogisContext\Domain\ValueObject\CustomView\UserFilterMode;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;

trait ValidatesViewConfiguration
{
    private const MAX_DESCRIPTION_LENGTH = 1000;

    /** @return array<string, list<mixed>> */
    private function configurationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:' . CustomViewTitle::MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:' . self::MAX_DESCRIPTION_LENGTH],
            'columns' => ['required', 'array', 'list', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct'],
            'filters' => ['present', 'array', 'list', 'max:' . ViewFilters::MAX_FILTERS],
            'filters.*' => ['array'],
            'filters.*.column' => ['required', 'string'],
            'filters.*.operator' => ['required', 'string', Rule::enum(type: FilterOperator::class)],
            'filters.*.value' => ['present'],
            'filters.*.user_filter' => ['nullable', 'string', Rule::enum(type: UserFilterMode::class)],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    private function title(): string
    {
        return $this->string(key: 'title')->toString();
    }

    private function description(): ?string
    {
        if (!$this->filled(key: 'description')) {
            return null;
        }

        return $this->string(key: 'description')->toString();
    }

    /**
     * @return array<mixed>
     */
    private function savedFilters(): array
    {
        return array_map(callback: fn (mixed $filter): mixed => $this->withDefaultUserFilter(filter: $filter), array: $this->listOf(key: 'filters'));
    }

    private function withDefaultUserFilter(mixed $filter): mixed
    {
        if (!is_array(value: $filter) || isset($filter['user_filter'])) {
            return $filter;
        }

        return [...$filter, 'user_filter' => UserFilterMode::Locked->value];
    }

    /**
     * @return array<mixed>
     */
    private function listOf(string $key): array
    {
        $value = $this->input(key: $key);

        if (!is_array(value: $value)) {
            return [];
        }

        return $value;
    }
}
