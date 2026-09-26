<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class ListCustomViewsEndpointTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerModel::factory()->create();
        $this->user = $this->userOf(customer: $customer, email: 'owner@logis.test');
        $colleague = $this->userOf(customer: $customer, email: 'colleague@logis.test');
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        CustomViewModel::factory()->asDefault()->create(attributes: ['user_id' => $this->user->id, 'title' => 'B mine']);
        CustomViewModel::factory()->ofType(type: ViewType::MyTickets)->public()->create(attributes: ['user_id' => $colleague->id, 'title' => 'A shared']);
        CustomViewModel::factory()->create(attributes: ['user_id' => $colleague->id, 'title' => 'Colleague private']);
        CustomViewModel::factory()->public()->create(attributes: ['user_id' => $stranger->id, 'title' => 'Stranger public']);
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson(uri: '/api/v1/views')->assertUnauthorized();
    }

    public function test_it_lists_the_own_views_and_the_public_views_of_the_customer(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->user))
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'Views')
            ->assertJsonPath(path: 'meta.total', expect: 2)
            ->assertJsonPath(path: 'data.0.title', expect: 'A shared')
            ->assertJsonPath(path: 'data.0.is_owner', expect: false)
            ->assertJsonPath(path: 'data.1.title', expect: 'B mine')
            ->assertJsonPath(path: 'data.1.is_default', expect: true)
            ->assertJsonStructure(structure: [
                'data' => [['id', 'title', 'description', 'view_type', 'is_default', 'is_public', 'is_owner']],
                'meta' => ['timestamp', 'version', 'total', 'execution_time_ms'],
            ]);
    }

    public function test_it_filters_by_type(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->user), path: '?type=my_tickets')
            ->assertOk()
            ->assertJsonPath(path: 'meta.total', expect: 1)
            ->assertJsonPath(path: 'data.0.title', expect: 'A shared');
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->user), path: '?type=my_invoices')
            ->assertUnprocessable()
            ->assertJsonStructure(structure: ['data' => ['type']]);
    }

    public function test_a_user_without_a_customer_gets_an_empty_list(): void
    {
        $this->callViews(method: 'GET', token: $this->tokenOf(user: $this->userOf(customer: null, email: 'nobody@logis.test')))
            ->assertOk()
            ->assertJsonPath(path: 'data', expect: [])
            ->assertJsonPath(path: 'meta.total', expect: 0);
    }
}
