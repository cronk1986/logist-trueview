<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class DeleteCustomViewEndpointTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private CustomerModel $customer;
    private UserModel $owner;
    private CustomViewModel $view;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerModel::factory()->create();
        $this->owner = $this->userOf(customer: $this->customer, email: 'owner@logis.test');
        $this->view = CustomViewModel::factory()->asDefault()->public()->create(attributes: ['user_id' => $this->owner->id]);
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->deleteJson(uri: '/api/v1/views/' . $this->view->id)->assertUnauthorized();
    }

    public function test_the_owner_deletes_the_view(): void
    {
        $this->callViews(method: 'DELETE', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id)
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'View deleted')
            ->assertJsonPath(path: 'data', expect: []);

        $this->assertNull($this->view->fresh());
    }

    public function test_deleting_the_default_leaves_the_type_without_one(): void
    {
        $other = CustomViewModel::factory()->create(attributes: ['user_id' => $this->owner->id]);

        $this->callViews(method: 'DELETE', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id)->assertOk();

        $this->assertFalse($other->fresh()?->is_default);
    }

    public function test_a_colleague_cannot_delete_a_public_view(): void
    {
        $colleague = $this->userOf(customer: $this->customer, email: 'colleague@logis.test');

        $this->callViews(method: 'DELETE', token: $this->tokenOf(user: $colleague), path: '/' . $this->view->id)->assertForbidden();

        $this->assertNotNull($this->view->fresh());
    }

    public function test_a_user_of_another_customer_gets_not_found(): void
    {
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        $this->callViews(method: 'DELETE', token: $this->tokenOf(user: $stranger), path: '/' . $this->view->id)->assertNotFound();

        $this->assertNotNull($this->view->fresh());
    }
}
