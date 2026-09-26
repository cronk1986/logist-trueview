<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\CustomView;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Command\CustomView\CreateCustomView;
use Logis\LogisContext\Application\Query\CustomView\GetCustomViewByTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class CreateCustomViewRequest extends FormRequest
{
    use ValidatesViewConfiguration;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->configurationRules(),
            'view_type' => ['required', 'string', Rule::enum(type: ViewType::class)],
        ];
    }

    public function toCommand(): CreateCustomView
    {
        return new CreateCustomView(
            userId: $this->userId(),
            title: $this->title(),
            description: $this->description(),
            viewType: $this->string(key: 'view_type')->toString(),
            columns: $this->listOf(key: 'columns'),
            filters: $this->listOf(key: 'filters'),
            isPublic: $this->boolean(key: 'is_public'),
        );
    }

    public function toQuery(): GetCustomViewByTitle
    {
        return new GetCustomViewByTitle(userId: $this->userId(), title: $this->title());
    }

    private function userId(): int
    {
        return CurrentAccessToken::of(request: $this)->tokenable_id;
    }
}
