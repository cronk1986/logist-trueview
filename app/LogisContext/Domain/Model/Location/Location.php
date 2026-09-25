<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\Location;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\ValueObject\Location\LocationId;

final class Location
{
    public function __construct(
        private readonly LocationId $id,
        private readonly Customer $customer,
        private readonly string $title,
        private readonly ?string $fullAddress,
        private readonly ?string $city,
        private readonly ?string $state,
        private readonly ?string $postalCode,
    ) {
    }

    public function id(): LocationId
    {
        return $this->id;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function fullAddress(): ?string
    {
        return $this->fullAddress;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }
}
