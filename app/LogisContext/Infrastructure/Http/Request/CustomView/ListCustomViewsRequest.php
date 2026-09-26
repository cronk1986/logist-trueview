<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Query\CustomView\ListCustomViews;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class ListCustomViewsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::enum(type: ViewType::class)],
        ];
    }

    public function toQuery(): ListCustomViews
    {
        return new ListCustomViews(
            userId: CurrentAccessToken::of(request: $this)->tokenable_id,
            viewType: $this->viewType(),
        );
    }

    private function viewType(): ?string
    {
        if (!$this->filled(key: 'type')) {
            return null;
        }

        return $this->string(key: 'type')->toString();
    }
}
