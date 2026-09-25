<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\Customer\Customer;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewTitle;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewColumns;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewFilters;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewMother
{
    public static function create(int $id = 1, ?User $user = null, ?Customer $customer = null, bool $isDefault = false, bool $isPublic = false): CustomView
    {
        $user ??= UserMother::create(customer: CustomerMother::create());
        $customer ??= $user->customer() ?? CustomerMother::create();

        return new CustomView(
            id: CustomViewId::fromInt(value: $id),
            user: $user,
            customer: $customer,
            title: CustomViewTitle::fromString(value: 'Zebra scanners'),
            description: 'Every Zebra device',
            type: ViewType::MyAssets,
            columns: ViewColumns::fromList(columns: ['serial_number', 'model']),
            filters: ViewFilters::fromList(filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']]),
            isDefault: $isDefault,
            isPublic: $isPublic,
        );
    }
}
