<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Query\CustomView\ExecuteCustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\FilterOperator;
use Logis\LogisContext\Domain\ValueObject\CustomView\SortDirection;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewField;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;
use Logis\LogisContext\Infrastructure\Http\Rule\ValidUtf8;

final class ExecuteCustomViewRequest extends FormRequest
{
    private const MAX_CURSOR_LENGTH = 4096;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'between:' . ViewPageSize::MIN . ',' . ViewPageSize::MAX],
            'cursor' => ['nullable', 'string', 'max:' . self::MAX_CURSOR_LENGTH],
            'sort' => ['nullable', 'array:field,direction'],
            'sort.field' => ['required_with:sort', 'string'],
            'sort.direction' => ['nullable', 'string', Rule::enum(type: SortDirection::class)],
            'q' => ['nullable', 'string', 'max:' . ViewField::MAX_TEXT_LENGTH, new ValidUtf8()],
            'filters' => ['nullable', 'array', 'list', 'max:' . ViewFilters::MAX_FILTERS],
            'filters.*' => ['array'],
            'filters.*.column' => ['required', 'string'],
            'filters.*.operator' => ['required', 'string', Rule::enum(type: FilterOperator::class)],
            'filters.*.value' => ['present'],
            'filters.*.user_filter' => ['prohibited'],
        ];
    }

    public function toQuery(int $id): ExecuteCustomView
    {
        return new ExecuteCustomView(
            userId: CurrentAccessToken::userId(request: $this),
            viewId: $id,
            limit: $this->limit(),
            cursor: $this->optionalString(key: 'cursor'),
            sortField: $this->optionalString(key: 'sort.field'),
            sortDirection: $this->optionalString(key: 'sort.direction'),
            term: $this->optionalString(key: 'q'),
            filters: $this->filters(),
        );
    }

    /**
     * @return array<mixed>
     */
    private function filters(): array
    {
        $filters = $this->input(key: 'filters');

        if (!is_array(value: $filters)) {
            return [];
        }

        return $filters;
    }

    private function limit(): int
    {
        if (!$this->filled(key: 'limit')) {
            return ViewPageSize::DEFAULT;
        }

        return $this->integer(key: 'limit');
    }

    private function optionalString(string $key): ?string
    {
        if (!$this->filled(key: $key)) {
            return null;
        }

        return $this->string(key: $key)->toString();
    }
}
