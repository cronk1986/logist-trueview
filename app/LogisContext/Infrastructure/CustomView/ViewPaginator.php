<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use BackedEnum;
use DateTimeInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewDefinition;

/**
 * @phpstan-import-type Row from ViewPage
 * @phpstan-import-type Reference from ViewPage
 */
final class ViewPaginator
{
    private const ID = 'id';

    public function __construct(private readonly ViewFilterApplier $filters)
    {
    }

    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     * @param array<string, FieldMapping> $fields
     */
    public function paginate(Builder $query, array $fields, ViewExecutionCriteria $criteria): ViewPage
    {
        $definition = $criteria->definition;

        $this->applyFilters(query: $query, fields: $fields, definition: $definition);

        $sortColumn = $this->mapping(fields: $fields, definition: $definition, field: $criteria->sort->field())->column();
        $orderColumns = array_values(array: array_unique(array: [$sortColumn, self::ID]));
        $cursor = ViewCursor::decode(encoded: $criteria->cursor, orderColumns: $orderColumns);

        $query->with(relations: $this->relations(fields: $fields, definition: $definition));

        foreach ($orderColumns as $column) {
            $query->orderBy($column, $criteria->sort->direction()->value);
        }

        $page = $query->cursorPaginate(
            perPage: $criteria->pageSize->value(),
            columns: $this->selectedColumns(fields: $fields, definition: $definition, sortColumn: $sortColumn),
            cursor: $cursor,
        );

        $rows = [];

        foreach ($page->items() as $model) {
            $rows[] = $this->row(model: $model, fields: $fields, definition: $definition);
        }

        return ViewPage::from(rows: $rows, nextCursor: $page->nextCursor()?->encode());
    }

    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     * @param array<string, FieldMapping> $fields
     */
    private function applyFilters(Builder $query, array $fields, ViewDefinition $definition): void
    {
        foreach ($definition->filters()->all() as $filter) {
            $field = $definition->catalog()->find(name: $filter->column());

            if ($field === null) {
                throw ViewFieldNotMappedException::field(field: $filter->column(), viewType: $definition->type()->value);
            }

            $this->filters->apply(
                query: $query,
                filter: $filter,
                field: $field,
                column: $this->mapping(fields: $fields, definition: $definition, field: $filter->column())->column(),
            );
        }
    }

    /**
     * @param array<string, FieldMapping> $fields
     * @return list<string>
     */
    private function selectedColumns(array $fields, ViewDefinition $definition, string $sortColumn): array
    {
        $columns = [self::ID, $sortColumn];

        foreach ($definition->columns()->toArray() as $field) {
            $columns[] = $this->mapping(fields: $fields, definition: $definition, field: $field)->column();
        }

        return array_values(array: array_unique(array: $columns));
    }

    /**
     * @param array<string, FieldMapping> $fields
     * @return list<string>
     */
    private function relations(array $fields, ViewDefinition $definition): array
    {
        $relations = [];

        foreach ($definition->columns()->toArray() as $field) {
            $mapping = $this->mapping(fields: $fields, definition: $definition, field: $field);
            $relation = $mapping->relation();

            if ($relation !== null) {
                $relations[] = sprintf('%s:%s,%s', $relation, self::ID, $mapping->label());
            }
        }

        return $relations;
    }

    /**
     * @param array<string, FieldMapping> $fields
     * @return Row
     */
    private function row(Model $model, array $fields, ViewDefinition $definition): array
    {
        $row = [self::ID => $this->scalar(value: $model->getAttribute(key: self::ID))];

        foreach ($definition->columns()->toArray() as $field) {
            $mapping = $this->mapping(fields: $fields, definition: $definition, field: $field);
            $row[$field] = $this->cell(model: $model, mapping: $mapping);
        }

        return $row;
    }

    /**
     * @return scalar|Reference|null
     */
    private function cell(Model $model, FieldMapping $mapping): string|int|float|bool|array|null
    {
        $relation = $mapping->relation();

        if ($relation === null) {
            return $this->scalar(value: $model->getAttribute(key: $mapping->column()));
        }

        $related = $model->getRelation(relation: $relation);

        if (!$related instanceof Model) {
            return null;
        }

        return [
            'id' => (int) $related->getKey(),
            'label' => $this->label(value: $related->getAttribute(key: (string) $mapping->label())),
        ];
    }

    private function scalar(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(date: $value)->utc()->toIso8601ZuluString();
        }

        if ($value === null || is_scalar(value: $value)) {
            return $value;
        }

        return null;
    }

    private function label(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $this->scalar(value: $value);
    }

    /**
     * @param array<string, FieldMapping> $fields
     */
    private function mapping(array $fields, ViewDefinition $definition, string $field): FieldMapping
    {
        $mapping = $fields[$field] ?? null;

        if ($mapping === null) {
            throw ViewFieldNotMappedException::field(field: $field, viewType: $definition->type()->value);
        }

        return $mapping;
    }
}
