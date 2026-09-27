<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class UpdateCustomViewEndpointTest extends TestCase
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
        $this->view = CustomViewModel::factory()->asDefault()->public()->create(attributes: ['user_id' => $this->owner->id, 'title' => 'Zebra scanners']);
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->putJson(uri: '/api/v1/views/' . $this->view->id, data: $this->payload())->assertUnauthorized();
    }

    public function test_the_owner_replaces_the_view_without_returning_data(): void
    {
        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: $this->payload())
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'message', expect: 'View updated')
            ->assertJsonPath(path: 'data', expect: []);

        $stored = $this->view->refresh();
        $this->assertSame('Retired devices', $stored->title);
        $this->assertNull($stored->description);
        $this->assertSame(['serial_number', 'status'], $stored->columns);
        $this->assertFalse($stored->is_public);
        $this->assertTrue($stored->is_default);
    }

    public function test_a_filter_sent_without_a_mode_or_with_a_null_mode_is_locked(): void
    {
        $payload = [...$this->payload(), 'filters' => [
            ['column' => 'status', 'operator' => 'eq', 'value' => 'retired'],
            ['column' => 'model', 'operator' => 'eq', 'value' => 'TC52', 'user_filter' => null],
        ]];

        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: $payload)
            ->assertOk();

        $this->assertSame(['locked', 'locked'], array_column(array: $this->view->refresh()->filters, column_key: 'user_filter'));
    }

    public function test_the_current_type_may_be_sent(): void
    {
        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: [...$this->payload(), 'view_type' => 'my_assets'])
            ->assertOk();
    }

    public function test_the_type_cannot_change(): void
    {
        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: [...$this->payload(), 'view_type' => 'my_tickets'])
            ->assertUnprocessable()
            ->assertJsonPath(path: 'message', expect: 'The type of a view cannot change. This view stays my_assets.');
    }

    public function test_a_title_of_another_view_of_the_owner_is_a_conflict(): void
    {
        CustomViewModel::factory()->create(attributes: ['user_id' => $this->owner->id, 'title' => 'Retired devices']);

        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: $this->payload())
            ->assertConflict();
    }

    public function test_a_colleague_cannot_change_a_public_view(): void
    {
        $colleague = $this->userOf(customer: $this->customer, email: 'colleague@logis.test');

        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $colleague), path: '/' . $this->view->id, data: $this->payload())
            ->assertForbidden();

        $this->assertSame('Zebra scanners', $this->view->fresh()?->title);
    }

    public function test_a_user_of_another_customer_gets_not_found(): void
    {
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $stranger), path: '/' . $this->view->id, data: $this->payload())
            ->assertNotFound();
    }

    public function test_a_definition_outside_the_catalog_is_rejected(): void
    {
        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: [...$this->payload(), 'columns' => ['deleted_at']])
            ->assertUnprocessable();

        $this->assertSame('Zebra scanners', $this->view->fresh()?->title);
    }

    public function test_the_structure_is_validated(): void
    {
        $this->callViews(method: 'PUT', token: $this->tokenOf(user: $this->owner), path: '/' . $this->view->id, data: ['title' => 'Only a title'])
            ->assertUnprocessable()
            ->assertJsonStructure(structure: ['data' => ['columns', 'filters']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Retired devices',
            'description' => null,
            'columns' => ['serial_number', 'status'],
            'filters' => [['column' => 'status', 'operator' => 'eq', 'value' => 'retired', 'user_filter' => 'locked']],
        ];
    }
}
