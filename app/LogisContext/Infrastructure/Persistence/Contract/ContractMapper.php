<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class ContractMapper
{
    public function __construct(private readonly CustomerMapper $customerMapper)
    {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations());
    }

    public function toDomain(ContractModel $model): Contract
    {
        return new Contract(
            id: ContractId::fromInt(value: $model->id),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            name: $model->name,
            status: $model->status,
        );
    }
}
