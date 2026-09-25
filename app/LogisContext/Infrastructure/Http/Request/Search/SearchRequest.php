<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Query\GlobalSearch\GlobalSearch;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Domain\ValueObject\Search\SearchLimit;
use Logis\LogisContext\Infrastructure\Http\CurrentAccessToken;

final class SearchRequest extends FormRequest
{
    private const ENTITY_SEPARATOR = ',';

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'between:' . SearchLimit::MIN . ',' . SearchLimit::MAX],
            'entities' => ['nullable', 'array'],
            'entities.*' => ['string', Rule::enum(type: SearchableEntity::class)],
        ];
    }

    public function toQuery(): GlobalSearch
    {
        return new GlobalSearch(
            userId: CurrentAccessToken::of(request: $this)->tokenable_id,
            term: $this->string(key: 'q')->toString(),
            limit: $this->integer(key: 'limit', default: SearchLimit::DEFAULT),
            entities: $this->entities(),
        );
    }

    protected function prepareForValidation(): void
    {
        $entities = $this->input(key: 'entities');

        if (!is_string(value: $entities)) {
            return;
        }

        $this->merge(input: ['entities' => $this->splitEntities(entities: $entities)]);
    }

    /**
     * @return list<string>
     */
    private function splitEntities(string $entities): array
    {
        $values = array_map(callback: trim(...), array: explode(separator: self::ENTITY_SEPARATOR, string: $entities));

        return array_values(array: array_filter(array: $values, callback: fn (string $value): bool => $value !== ''));
    }

    /**
     * @return list<string>
     */
    private function entities(): array
    {
        $requested = $this->input(key: 'entities');

        if (!is_array(value: $requested) || $requested === []) {
            return array_map(callback: fn (SearchableEntity $entity): string => $entity->value, array: SearchableEntity::cases());
        }

        return array_values(array: array_unique(array: array_map(callback: strval(...), array: $requested)));
    }
}
