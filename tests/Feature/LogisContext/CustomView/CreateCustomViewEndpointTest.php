<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateCustomViewEndpointTest extends TestCase
{
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->userOf(customer: CustomerModel::factory()->create(), email: 'owner@logis.test');
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->postJson(uri: '/api/v1/views', data: $this->payload())->assertUnauthorized();
    }

    public function test_it_creates_the_view_and_returns_its_details(): void
    {
        $response = $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload());

        $response->assertCreated()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'View created')
            ->assertJsonPath(path: 'data.title', expect: 'Zebra scanners')
            ->assertJsonPath(path: 'data.view_type', expect: 'my_assets')
            ->assertJsonPath(path: 'data.columns', expect: ['serial_number', 'location'])
            ->assertJsonPath(path: 'data.filters.1.value', expect: ['2024-01-01', '2024-06-30'])
            ->assertJsonPath(path: 'data.is_public', expect: true)
            ->assertJsonPath(path: 'data.is_default', expect: false)
            ->assertJsonPath(path: 'data.is_owner', expect: true);

        $stored = CustomViewModel::query()->findOrFail(id: (int) $response->json(key: 'data.id'));
        $this->assertSame($this->user->id, $stored->user_id);
        $this->assertSame($this->user->customer_id, $stored->customer_id);
    }

    public function test_is_default_in_the_body_is_ignored(): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: [...$this->payload(), 'is_default' => true])
            ->assertCreated()
            ->assertJsonPath(path: 'data.is_default', expect: false);
    }

    public function test_is_public_defaults_to_false(): void
    {
        $payload = $this->payload();
        unset($payload['is_public']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $payload)
            ->assertCreated()
            ->assertJsonPath(path: 'data.is_public', expect: false);
    }

    public function test_a_title_the_owner_already_uses_is_a_conflict(): void
    {
        CustomViewModel::factory()->create(attributes: ['user_id' => $this->user->id, 'title' => 'Zebra scanners']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())
            ->assertConflict()
            ->assertJsonPath(path: 'message', expect: 'You already have a view called "Zebra scanners".');
    }

    public function test_a_user_without_a_customer_is_forbidden(): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->userOf(customer: null, email: 'nobody@logis.test')), data: $this->payload())
            ->assertForbidden()
            ->assertJsonPath(path: 'message', expect: 'A user without a customer cannot own views.');
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_structure_is_rejected(array $changes, string $field): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: [...$this->payload(), ...$changes])
            ->assertUnprocessable()
            ->assertJsonStructure(structure: ['data' => [$field]]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing title' => [['title' => null], 'title'],
            'title too long' => [['title' => str_repeat(string: 'a', times: 256)], 'title'],
            'description too long' => [['description' => str_repeat(string: 'a', times: 1001)], 'description'],
            'unknown view type' => [['view_type' => 'my_invoices'], 'view_type'],
            'no columns' => [['columns' => []], 'columns'],
            'duplicated columns' => [['columns' => ['model', 'model']], 'columns.0'],
            'filters missing' => [['filters' => null], 'filters'],
            'too many filters' => [['filters' => array_fill(start_index: 0, count: 21, value: ['column' => 'model', 'operator' => 'eq', 'value' => 'A'])], 'filters'],
            'unknown operator' => [['filters' => [['column' => 'model', 'operator' => 'like', 'value' => 'A']]], 'filters.0.operator'],
            'filter without column' => [['filters' => [['operator' => 'eq', 'value' => 'A']]], 'filters.0.column'],
            'is_public not a boolean' => [['is_public' => 'yes'], 'is_public'],
        ];
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('catalogViolations')]
    public function test_a_definition_outside_the_catalog_is_rejected(array $changes, string $message): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: [...$this->payload(), ...$changes])
            ->assertUnprocessable()
            ->assertJsonPath(path: 'message', expect: $message);

        $this->assertSame(0, CustomViewModel::query()->count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function catalogViolations(): array
    {
        return [
            'restricted column' => [['columns' => ['serial_number', 'customer_id']], '"customer_id" is not a column of my_assets views.'],
            'column of another type' => [['view_type' => 'my_contracts', 'columns' => ['serial_number']], '"serial_number" is not a column of my_contracts views.'],
            'operator not allowed' => [['filters' => [['column' => 'model', 'operator' => 'gt', 'value' => 'A']]], 'The operator "gt" cannot be used on "model".'],
            'wrong value type' => [['filters' => [['column' => 'location', 'operator' => 'eq', 'value' => 'Main']]], 'The filter on "location" needs positive integer ids.'],
            'invalid date' => [['filters' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-13-01']]], 'The filter on "created_at" needs dates in the Y-m-d format.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'title' => 'Zebra scanners',
            'description' => 'Every Zebra device',
            'view_type' => 'my_assets',
            'columns' => ['serial_number', 'location'],
            'filters' => [
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra'],
                ['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-06-30']],
            ],
            'is_public' => true,
        ];
    }
}
