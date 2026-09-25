<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Exception\Asset;

use Logis\SharedContext\Domain\Exception\InvalidValueException;

final class AssetLinkedToAnotherCustomerException extends InvalidValueException
{
    public static function contract(int $assetId, int $contractId): self
    {
        return new self(message: sprintf('Asset %d cannot use contract %d, which belongs to another customer.', $assetId, $contractId));
    }

    public static function location(int $assetId, int $locationId): self
    {
        return new self(message: sprintf('Asset %d cannot be at location %d, which belongs to another customer.', $assetId, $locationId));
    }
}
