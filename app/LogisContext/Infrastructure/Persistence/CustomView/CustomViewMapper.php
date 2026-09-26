<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\LogisContext\Infrastructure\Persistence\User\UserMapper;
use Logis\SharedContext\Infrastructure\Persistence\CorruptedRecordException;
use Logis\SharedContext\Domain\Exception\InvalidValueException;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class CustomViewMapper
{
    public function __construct(
        private readonly UserMapper $userMapper,
        private readonly CustomerMapper $customerMapper,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            ...EagerLoad::nested(relation: 'user', relations: UserMapper::relations()),
            ...EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations()),
        ];
    }

    public function toDomain(CustomViewModel $model): CustomView
    {
        try {
            return $this->build(model: $model);
        } catch (InvalidValueException $exception) {
            throw CorruptedRecordException::in(table: $model->getTable(), id: $model->id, reason: $exception);
        }
    }

    private function build(CustomViewModel $model): CustomView
    {
        return new CustomView(
            id: CustomViewId::fromInt(value: $model->id),
            user: $this->userMapper->toDomain(model: $model->user),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            title: CustomViewTitle::fromString(value: $model->title),
            description: $model->description,
            type: $model->view_type,
            columns: ViewColumns::fromList(columns: $model->columns),
            filters: ViewFilters::fromList(filters: $model->filters),
            isDefault: $model->is_default,
            isPublic: $model->is_public,
        );
    }
}
