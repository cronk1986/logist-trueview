<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

interface CustomViewRepository
{
    public function findById(CustomViewId $id): ?CustomView;

    /**
     * @return list<CustomView>
     */
    public function findOwnedByOrPublicIn(UserId $owner, CustomerId $customer, ?ViewType $type): array;
}
