<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\Customer\CustomerMother;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class ViewPolicyTest extends TestCase
{
    private ViewPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ViewPolicy();
    }

    public function test_the_owner_sees_and_manages_a_private_view(): void
    {
        $owner = UserMother::create(id: 1, customer: CustomerMother::create(id: 1));
        $view = CustomViewMother::create(user: $owner);

        $this->assertTrue($this->policy->canSee(user: $owner, view: $view));
        $this->assertTrue($this->policy->canManage(user: $owner, view: $view));
    }

    public function test_a_colleague_sees_a_public_view_of_the_same_customer_but_cannot_manage_it(): void
    {
        $customer = CustomerMother::create(id: 1);
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: $customer), isPublic: true);
        $colleague = UserMother::create(id: 2, email: 'colleague@logis.test', customer: $customer);

        $this->assertTrue($this->policy->canSee(user: $colleague, view: $view));
        $this->assertFalse($this->policy->canManage(user: $colleague, view: $view));
    }

    public function test_a_colleague_does_not_see_a_private_view(): void
    {
        $customer = CustomerMother::create(id: 1);
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: $customer));
        $colleague = UserMother::create(id: 2, email: 'colleague@logis.test', customer: $customer);

        $this->assertFalse($this->policy->canSee(user: $colleague, view: $view));
    }

    public function test_a_user_of_another_customer_does_not_see_a_public_view(): void
    {
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: CustomerMother::create(id: 1)), isPublic: true);
        $stranger = UserMother::create(id: 2, email: 'stranger@logis.test', customer: CustomerMother::create(id: 2, erpId: 'C-2'));

        $this->assertFalse($this->policy->canSee(user: $stranger, view: $view));
    }

    public function test_a_user_of_a_sub_account_does_not_see_a_public_view_of_the_parent(): void
    {
        $parent = CustomerMother::create(id: 1);
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: $parent), isPublic: true);
        $subAccountUser = UserMother::create(id: 2, email: 'sub@logis.test', customer: CustomerMother::create(id: 2, erpId: 'C-2', parent: $parent));

        $this->assertFalse($this->policy->canSee(user: $subAccountUser, view: $view));
    }

    public function test_a_user_without_a_customer_sees_no_public_view(): void
    {
        $view = CustomViewMother::create(user: UserMother::create(id: 1, customer: CustomerMother::create(id: 1)), isPublic: true);

        $this->assertFalse($this->policy->canSee(user: UserMother::create(id: 2, customer: null), view: $view));
    }
}
