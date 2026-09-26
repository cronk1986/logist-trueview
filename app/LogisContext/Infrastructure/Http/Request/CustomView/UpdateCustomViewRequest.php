<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Command\CustomView\UpdateCustomView;
use Logis\LogisContext\Application\Query\CustomView\GetCustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class UpdateCustomViewRequest extends FormRequest
{
    use ValidatesViewConfiguration;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->configurationRules(),
            'view_type' => ['sometimes', 'string', Rule::enum(type: ViewType::class)],
        ];
    }

    public function toCommand(int $id): UpdateCustomView
    {
        return new UpdateCustomView(
            userId: CurrentAccessToken::userId(request: $this),
            viewId: $id,
            title: $this->title(),
            description: $this->description(),
            viewType: $this->viewType(),
            columns: $this->listOf(key: 'columns'),
            filters: $this->listOf(key: 'filters'),
            isPublic: $this->boolean(key: 'is_public'),
        );
    }

    public function toQuery(int $id): GetCustomView
    {
        return new GetCustomView(userId: CurrentAccessToken::userId(request: $this), viewId: $id);
    }

    private function viewType(): ?string
    {
        if (!$this->filled(key: 'view_type')) {
            return null;
        }

        return $this->string(key: 'view_type')->toString();
    }
}
