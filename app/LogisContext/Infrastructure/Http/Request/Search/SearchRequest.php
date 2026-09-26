<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Http\Request\Search;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Logis\LogisContext\Application\Query\Search\SearchService;
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
            'q' => ['required', 'string', $this->validUtf8()],
            'limit' => ['nullable', 'integer', 'between:' . SearchLimit::MIN . ',' . SearchLimit::MAX],
            'entities' => ['nullable', 'array'],
            'entities.*' => ['string', Rule::enum(type: SearchableEntity::class)],
        ];
    }

    public function toQuery(): SearchService
    {
        return new SearchService(
            userId: CurrentAccessToken::of(request: $this)->tokenable_id,
            term: $this->string(key: 'q')->toString(),
            limit: $this->limit(),
            entities: $this->entities(),
        );
    }

    protected function prepareForValidation(): void
    {
        $entities = $this->input(key: 'entities');

        if (is_string(value: $entities)) {
            $this->merge(input: ['entities' => $this->withoutEmptyValues(values: explode(separator: self::ENTITY_SEPARATOR, string: $entities))]);

            return;
        }

        if (is_array(value: $entities)) {
            $this->merge(input: ['entities' => $this->withoutEmptyValues(values: $entities)]);
        }
    }

    private function validUtf8(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string(value: $value) && !mb_check_encoding(value: $value, encoding: 'UTF-8')) {
                $fail('The :attribute field must be valid UTF-8 text.');
            }
        };
    }

    /**
     * @param array<mixed> $values
     * @return list<mixed>
     */
    private function withoutEmptyValues(array $values): array
    {
        $trimmed = array_map(callback: fn (mixed $value): mixed => is_string(value: $value) ? trim(string: $value) : $value, array: $values);

        return array_values(array: array_filter(array: $trimmed, callback: fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function limit(): int
    {
        if (!$this->filled(key: 'limit')) {
            return SearchLimit::DEFAULT;
        }

        return $this->integer(key: 'limit');
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
