<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class ShowCustomViewEndpointTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private CustomerModel $customer;
    private UserModel $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerModel::factory()->create();
        $this->owner = $this->userOf(customer: $this->customer, email: 'owner@logis.test');
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson(uri: '/api/v1/views/1')->assertUnauthorized();
    }

    public function test_the_owner_gets_the_details(): void
    {
        $view = CustomViewModel::factory()->asDefault()->create(attributes: [
            'user_id' => $this->owner->id,
            'title' => 'Zebra scanners',
            'columns' => ['serial_number', 'location'],
            'filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']],
        ]);

        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->owner), path: '/' . $view->id)
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'message', expect: 'View details')
            ->assertJsonPath(path: 'data.id', expect: $view->id)
            ->assertJsonPath(path: 'data.view_type', expect: 'my_assets')
            ->assertJsonPath(path: 'data.columns', expect: ['serial_number', 'location'])
            ->assertJsonPath(path: 'data.filters.0.value', expect: 'Zebra')
            ->assertJsonPath(path: 'data.is_default', expect: true)
            ->assertJsonPath(path: 'data.is_owner', expect: true)
            ->assertJsonPath(path: 'data.owner.id', expect: $this->owner->id);
    }

    public function test_a_colleague_sees_a_public_view_but_not_as_owner(): void
    {
        $view = CustomViewModel::factory()->asDefault()->public()->create(attributes: ['user_id' => $this->owner->id]);
        $colleague = $this->userOf(customer: $this->customer, email: 'colleague@logis.test');

        $this->callViews(method: 'GET', token: $this->tokenOf(user: $colleague), path: '/' . $view->id)
            ->assertOk()
            ->assertJsonPath(path: 'data.is_owner', expect: false)
            ->assertJsonPath(path: 'data.is_default', expect: false);
    }

    public function test_a_private_view_of_a_colleague_is_not_found(): void
    {
        $view = CustomViewModel::factory()->create(attributes: ['user_id' => $this->owner->id]);
        $colleague = $this->userOf(customer: $this->customer, email: 'colleague@logis.test');

        $this->callViews(method: 'GET', token: $this->tokenOf(user: $colleague), path: '/' . $view->id)
            ->assertNotFound()
            ->assertJsonPath(path: 'message', expect: sprintf('View %d not found.', $view->id));
    }

    public function test_a_public_view_of_another_customer_is_not_found(): void
    {
        $view = CustomViewModel::factory()->public()->create(attributes: ['user_id' => $this->owner->id]);
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        $this->callViews(method: 'GET', token: $this->tokenOf(user: $stranger), path: '/' . $view->id)->assertNotFound();
    }

    public function test_a_missing_view_is_not_found(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->owner), path: '/999')->assertNotFound();
    }

    public function test_a_non_numeric_id_does_not_match_the_route(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->owner), path: '/abc')->assertNotFound();
    }
}
