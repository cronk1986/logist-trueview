<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Ticket;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class TicketLinkedToAnotherCustomerException extends InvalidValueException
{
    public static function asset(int $ticketId, int $assetId): self
    {
        return new self(message: sprintf('Ticket %d cannot refer to asset %d, which belongs to another customer.', $ticketId, $assetId));
    }
}
