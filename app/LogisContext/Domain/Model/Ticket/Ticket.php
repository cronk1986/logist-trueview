<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Ticket;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Exception\Ticket\TicketLinkedToAnotherCustomerException;
use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatus;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\Ticket\IncidentNumber;
use Logis\LogisContext\Domain\ValueObject\Ticket\RepairId;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;

final class Ticket
{
    public function __construct(
        private readonly TicketId $id,
        private readonly Customer $customer,
        private readonly ?Asset $asset,
        private readonly RepairId $repairId,
        private readonly IncidentNumber $incidentNumber,
        private readonly string $subject,
        private readonly TicketStatus $status,
        private readonly string $issueDescription,
        private readonly User $createdBy,
        private readonly CarbonImmutable $createdAt,
    ) {
        $this->ensureTheAssetBelongsToTheCustomer();
    }

    public function id(): TicketId
    {
        return $this->id;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function asset(): ?Asset
    {
        return $this->asset;
    }

    public function repairId(): RepairId
    {
        return $this->repairId;
    }

    public function incidentNumber(): IncidentNumber
    {
        return $this->incidentNumber;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function status(): TicketStatus
    {
        return $this->status;
    }

    public function issueDescription(): string
    {
        return $this->issueDescription;
    }

    public function createdBy(): User
    {
        return $this->createdBy;
    }

    public function createdAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    private function ensureTheAssetBelongsToTheCustomer(): void
    {
        if ($this->asset === null) {
            return;
        }

        if (!$this->asset->customer()->isSameAs(other: $this->customer)) {
            throw TicketLinkedToAnotherCustomerException::asset(ticketId: $this->id->value(), assetId: $this->asset->id()->value());
        }
    }
}
