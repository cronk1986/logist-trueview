<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\Ticket;

use Logis\LogisContext\Domain\Exception\Ticket\TicketLinkedToAnotherCustomerException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Asset\AssetMother;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class TicketTest extends TestCase
{
    public function test_it_holds_its_customer_asset_status_and_creator(): void
    {
        $customer = CustomerMother::create(id: 4);
        $asset = AssetMother::create(customer: $customer);
        $creator = UserMother::create(id: 9, customer: $customer);

        $ticket = TicketMother::create(id: 2, customer: $customer, asset: $asset, createdBy: $creator);

        $this->assertSame(2, $ticket->id()->value());
        $this->assertSame($customer, $ticket->customer());
        $this->assertSame($asset, $ticket->asset());
        $this->assertSame('RMA-000001', $ticket->repairId()->value());
        $this->assertSame('INC-000001', $ticket->incidentNumber()->value());
        $this->assertSame('Scanner does not turn on', $ticket->subject());
        $this->assertSame('Open', $ticket->status()->name());
        $this->assertSame('The device stays off after a full charge.', $ticket->issueDescription());
        $this->assertSame($creator, $ticket->createdBy());
        $this->assertSame('2026-02-01 08:00:00', $ticket->createdAt()->format(format: 'Y-m-d H:i:s'));
    }

    public function test_the_asset_is_optional(): void
    {
        $this->assertNull(TicketMother::create()->asset());
    }

    public function test_a_ticket_cannot_refer_to_an_asset_of_another_customer(): void
    {
        $this->expectException(TicketLinkedToAnotherCustomerException::class);

        TicketMother::create(customer: CustomerMother::create(id: 1), asset: AssetMother::create(customer: CustomerMother::create(id: 2)));
    }

    public function test_the_creator_may_belong_to_another_customer(): void
    {
        $creator = UserMother::create(customer: CustomerMother::create(id: 2));

        $ticket = TicketMother::create(customer: CustomerMother::create(id: 1), createdBy: $creator);

        $this->assertSame($creator, $ticket->createdBy());
    }
}
