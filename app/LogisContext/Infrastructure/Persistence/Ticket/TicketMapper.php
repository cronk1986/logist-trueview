<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Persistence\Ticket;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Ticket\Ticket;
use Logis\LogisContext\Domain\ValueObject\Ticket\IncidentNumber;
use Logis\LogisContext\Domain\ValueObject\Ticket\RepairId;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetMapper;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\TicketStatusMapper;
use Logis\LogisContext\Infrastructure\Persistence\User\UserMapper;
use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;

final class TicketMapper
{
    public function __construct(
        private readonly CustomerMapper $customerMapper,
        private readonly AssetMapper $assetMapper,
        private readonly TicketStatusMapper $statusMapper,
        private readonly UserMapper $userMapper,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            ...EagerLoad::nested(relation: 'customer', relations: CustomerMapper::relations()),
            ...EagerLoad::nested(relation: 'asset', relations: AssetMapper::relations()),
            ...EagerLoad::nested(relation: 'status', relations: TicketStatusMapper::relations()),
            ...EagerLoad::nested(relation: 'creator', relations: UserMapper::relations()),
        ];
    }

    public function toDomain(TicketModel $model): Ticket
    {
        return new Ticket(
            id: TicketId::fromInt(value: $model->id),
            customer: $this->customerMapper->toDomain(model: $model->customer),
            asset: $this->asset(model: $model),
            repairId: RepairId::fromString(value: $model->repair_id),
            incidentNumber: IncidentNumber::fromString(value: $model->incident_number),
            subject: $model->subject,
            status: $this->statusMapper->toDomain(model: $model->status),
            issueDescription: $model->issue_description,
            createdBy: $this->userMapper->toDomain(model: $model->creator),
            createdAt: CarbonImmutable::instance(date: $model->created_at)->utc(),
        );
    }

    private function asset(TicketModel $model): ?Asset
    {
        if ($model->asset === null) {
            return null;
        }

        return $this->assetMapper->toDomain(model: $model->asset);
    }
}
