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
    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>> $filters
     */
    public static function create(
        ?int $id = 1,
        ?User $user = null,
        ?Customer $customer = null,
        string $title = 'Zebra scanners',
        ViewType $type = ViewType::MyAssets,
        array $columns = ['serial_number', 'model'],
        array $filters = [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']],
        bool $isDefault = false,
        bool $isPublic = false,
    ): CustomView {
        $user ??= UserMother::create(customer: CustomerMother::create());
        $customer ??= $user->customer() ?? CustomerMother::create();

        return new CustomView(
            id: $id === null ? null : CustomViewId::fromInt(value: $id),
            user: $user,
            customer: $customer,
            title: CustomViewTitle::fromString(value: $title),
            description: 'Every Zebra device',
            type: $type,
            columns: ViewColumns::fromList(columns: $columns),
            filters: ViewFilters::fromList(filters: $filters),
            isDefault: $isDefault,
            isPublic: $isPublic,
        );
    }
}
