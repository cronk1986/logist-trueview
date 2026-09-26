<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class SetDefaultCustomViewEndpointTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private CustomerModel $customer;
    private UserModel $owner;
    private CustomViewModel $currentDefault;
    private CustomViewModel $view;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerModel::factory()->create();
        $this->owner = $this->userOf(customer: $this->customer, email: 'owner@logis.test');
        $this->currentDefault = CustomViewModel::factory()->ofType(type: ViewType::MyAssets)->asDefault()->create(attributes: ['user_id' => $this->owner->id]);
        $this->view = CustomViewModel::factory()->ofType(type: ViewType::MyAssets)->public()->create(attributes: ['user_id' => $this->owner->id]);
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->postJson(uri: '/api/v1/views/' . $this->view->id . '/set-default')->assertUnauthorized();
    }

    public function test_the_owner_replaces_the_default_of_the_type(): void
    {
        $ticketsDefault = CustomViewModel::factory()->ofType(type: ViewType::MyTickets)->asDefault()->create(attributes: ['user_id' => $this->owner->id]);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id . '/set-default')
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'message', expect: 'View set as default')
            ->assertJsonPath(path: 'data.id', expect: $this->view->id)
            ->assertJsonPath(path: 'data.is_default', expect: true);

        $this->assertFalse($this->currentDefault->fresh()?->is_default);
        $this->assertTrue($ticketsDefault->fresh()?->is_default);
        $this->assertSame(1, CustomViewModel::query()->where(column: 'user_id', operator: $this->owner->id)->where(column: 'view_type', operator: 'my_assets')->where(column: 'is_default', operator: true)->count());
    }

    public function test_setting_the_default_twice_keeps_one_default(): void
    {
        $token = $this->tokenOf(user: $this->owner);

        $this->callViews(method: 'POST', token: $token, path: '/' . $this->view->id . '/set-default')->assertOk();
        $this->callViews(method: 'POST', token: $token, path: '/' . $this->view->id . '/set-default')->assertOk();

        $this->assertSame(1, CustomViewModel::query()->where(column: 'user_id', operator: $this->owner->id)->where(column: 'is_default', operator: true)->count());
    }

    public function test_a_colleague_cannot_set_a_public_view_as_default(): void
    {
        $colleague = $this->userOf(customer: $this->customer, email: 'colleague@logis.test');

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $colleague), path: '/' . $this->view->id . '/set-default')
            ->assertForbidden();

        $this->assertFalse($this->view->fresh()?->is_default);
    }

    public function test_a_user_of_another_customer_gets_not_found(): void
    {
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $stranger), path: '/' . $this->view->id . '/set-default')
            ->assertNotFound();
    }
}
