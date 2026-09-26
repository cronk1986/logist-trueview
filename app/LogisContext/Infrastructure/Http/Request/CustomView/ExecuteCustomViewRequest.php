<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Query\CustomView\ExecuteCustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\SortDirection;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewPageSize;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;
use Logis\LogisContext\Infrastructure\Http\Rule\ValidUtf8;

final class ExecuteCustomViewRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'between:' . ViewPageSize::MIN . ',' . ViewPageSize::MAX],
            'cursor' => ['nullable', 'string'],
            'sort' => ['nullable', 'array:field,direction'],
            'sort.field' => ['required_with:sort', 'string'],
            'sort.direction' => ['nullable', 'string', Rule::enum(type: SortDirection::class)],
            'q' => ['nullable', 'string', new ValidUtf8()],
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
        );
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
