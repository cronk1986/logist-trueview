<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Ticket;

use Carbon\CarbonImmutable;
use Logis\LogisContext\Domain\Model\Asset\Asset;
use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\Ticket\Ticket;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\Ticket\IncidentNumber;
use Logis\LogisContext\Domain\ValueObject\Ticket\RepairId;
use Logis\LogisContext\Domain\ValueObject\Ticket\TicketId;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\TicketStatus\TicketStatusMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class TicketMother
{
    public static function create(int $id = 1, ?Customer $customer = null, ?Asset $asset = null, ?User $createdBy = null): Ticket
    {
        $customer ??= CustomerMother::create();

        return new Ticket(
            id: TicketId::fromInt(value: $id),
            customer: $customer,
            asset: $asset,
            repairId: RepairId::fromString(value: 'RMA-000001'),
            incidentNumber: IncidentNumber::fromString(value: 'INC-000001'),
            subject: 'Scanner does not turn on',
            status: TicketStatusMother::create(),
            issueDescription: 'The device stays off after a full charge.',
            createdBy: $createdBy ?? UserMother::create(customer: $customer),
            createdAt: CarbonImmutable::parse(time: '2026-02-01 08:00:00', timezone: 'UTC'),
        );
    }
}
