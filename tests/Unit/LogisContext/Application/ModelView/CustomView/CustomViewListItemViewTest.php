<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\CustomView;

use Logis\LogisContext\Application\ModelView\CustomView\CustomViewListItemView;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\CustomView\CustomViewMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewListItemViewTest extends TestCase
{
    public function test_it_renders_the_summary_of_a_view(): void
    {
        $customer = CustomerMother::create();
        $view = CustomViewMother::create(id: 4, user: UserMother::create(id: 1, customer: $customer), isDefault: true, isPublic: true);

        $this->assertSame(
            ['id' => 4, 'title' => 'Zebra scanners', 'description' => 'Every Zebra device', 'view_type' => 'my_assets', 'is_default' => false, 'is_public' => true, 'is_owner' => false],
            CustomViewListItemView::from(view: $view, viewer: UserMother::create(id: 2, customer: $customer))->toArray(),
        );
    }
}
