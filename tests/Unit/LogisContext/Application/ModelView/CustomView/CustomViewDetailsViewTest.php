<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\CustomViewDetailsView;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotSavedException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewDetailsViewTest extends TestCase
{
    public function test_it_renders_the_view_for_its_owner(): void
    {
        $owner = UserMother::create(id: 3, name: 'Ada', customer: CustomerMother::create());
        $view = CustomViewMother::create(id: 8, user: $owner, isDefault: true);

        $this->assertSame(
            [
                'id' => 8,
                'title' => 'Zebra scanners',
                'description' => 'Every Zebra device',
                'view_type' => 'my_assets',
                'columns' => ['serial_number', 'model'],
                'filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked']],
                'is_default' => true,
                'is_public' => false,
                'is_owner' => true,
                'owner' => ['id' => 3, 'name' => 'Ada'],
            ],
            CustomViewDetailsView::from(view: $view, viewer: $owner)->toArray(),
        );
    }

    public function test_the_default_of_another_owner_is_not_shown_as_default(): void
    {
        $customer = CustomerMother::create();
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: $customer), isDefault: true, isPublic: true);

        $data = CustomViewDetailsView::from(view: $view, viewer: UserMother::create(id: 2, customer: $customer))->toArray();

        $this->assertFalse($data['is_default']);
        $this->assertFalse($data['is_owner']);
    }

    public function test_an_unsaved_view_cannot_be_rendered(): void
    {
        $owner = UserMother::create(customer: CustomerMother::create());

        $this->expectException(CustomViewNotSavedException::class);

        CustomViewDetailsView::from(view: CustomViewMother::create(id: null, user: $owner), viewer: $owner);
    }
}
