<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Contract;

use Logis\LogisContext\Domain\Model\Contract\Contract;
use Logis\LogisContext\Domain\Model\Contract\ContractRepository;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractId;

final class EloquentContractRepository implements ContractRepository
{
    public function __construct(private readonly ContractMapper $mapper)
    {
    }

    public function findById(ContractId $id): ?Contract
    {
        $model = ContractModel::query()->with(relations: ContractMapper::relations())->find(id: $id->value());

        if ($model === null) {
            return null;
        }

        return $this->mapper->toDomain(model: $model);
    }
}
