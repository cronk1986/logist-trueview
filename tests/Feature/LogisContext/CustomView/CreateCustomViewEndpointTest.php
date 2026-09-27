<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
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

    public function test_it_creates_the_view_without_returning_data(): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())
            ->assertCreated()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'View created')
            ->assertJsonPath(path: 'data', expect: []);

        $stored = $this->storedView();
        $this->assertSame($this->user->id, $stored->user_id);
        $this->assertSame($this->user->customer_id, $stored->customer_id);
        $this->assertSame('Every Zebra device', $stored->description);
        $this->assertSame(ViewType::MyAssets, $stored->view_type);
        $this->assertSame(['serial_number', 'location'], $stored->columns);
        $this->assertSame(['2024-01-01', '2024-06-30'], $stored->filters[1]['value'] ?? null);
        $this->assertTrue($stored->is_public);
        $this->assertFalse($stored->is_default);
    }

    public function test_a_filter_without_a_mode_is_locked(): void
    {
        $payload = [...$this->payload(), 'filters' => [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']]];

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $payload)->assertCreated();

        $this->assertSame('locked', $this->storedView()->filters[0]['user_filter'] ?? null);
    }

    public function test_the_three_modes_are_stored(): void
    {
        $payload = [...$this->payload(), 'filters' => [
            ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive'], 'user_filter' => 'restricted'],
            ['column' => 'model', 'operator' => 'contains', 'value' => 'TC', 'user_filter' => 'open'],
            ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked'],
        ]];

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $payload)->assertCreated();

        $this->assertSame(['restricted', 'open', 'locked'], array_column(array: $this->storedView()->filters, column_key: 'user_filter'));
    }

    public function test_is_default_in_the_body_is_ignored(): void
    {
        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: [...$this->payload(), 'is_default' => true])
            ->assertCreated();

        $this->assertFalse($this->storedView()->is_default);
    }

    public function test_is_public_defaults_to_false(): void
    {
        $payload = $this->payload();
        unset($payload['is_public']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $payload)
            ->assertCreated();

        $this->assertFalse($this->storedView()->is_public);
    }

    public function test_a_title_the_owner_already_uses_is_a_conflict(): void
    {
        CustomViewModel::factory()->create(attributes: ['user_id' => $this->user->id, 'title' => 'Zebra scanners']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())
            ->assertConflict()
            ->assertJsonPath(path: 'message', expect: 'You already have a view called "Zebra scanners".');
    }

    public function test_a_title_that_differs_only_in_case_is_a_conflict(): void
    {
        CustomViewModel::factory()->create(attributes: ['user_id' => $this->user->id, 'title' => 'ZEBRA SCANNERS']);

        $this->callViews(method: 'POST', token: $this->tokenOf(user: $this->user), data: $this->payload())
            ->assertConflict();
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
            'too many filters' => [['filters' => array_fill(start_index: 0, count: 21, value: ['column' => 'model', 'operator' => 'eq', 'value' => 'A', 'user_filter' => 'locked'])], 'filters'],
            'unknown operator' => [['filters' => [['column' => 'model', 'operator' => 'like', 'value' => 'A', 'user_filter' => 'locked']]], 'filters.0.operator'],
            'filter without column' => [['filters' => [['operator' => 'eq', 'value' => 'A']]], 'filters.0.column'],
            'is_public not a boolean' => [['is_public' => 'yes'], 'is_public'],
            'unknown user filter mode' => [['filters' => [['column' => 'model', 'operator' => 'eq', 'value' => 'A', 'user_filter' => 'public']]], 'filters.0.user_filter'],
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
            'operator not allowed' => [['filters' => [['column' => 'model', 'operator' => 'gt', 'value' => 'A', 'user_filter' => 'locked']]], 'The operator "gt" cannot be used on "model".'],
            'wrong value type' => [['filters' => [['column' => 'location', 'operator' => 'eq', 'value' => 'Main', 'user_filter' => 'locked']]], 'The filter on "location" needs positive integer ids.'],
            'mixed user filter modes' => [['filters' => [
                ['column' => 'created_at', 'operator' => 'gte', 'value' => '2024-01-01', 'user_filter' => 'open'],
                ['column' => 'created_at', 'operator' => 'lte', 'value' => '2024-12-31', 'user_filter' => 'locked'],
            ]], 'The filters on "created_at" must share the same user_filter mode.'],
            'restricted with contains' => [['filters' => [['column' => 'model', 'operator' => 'contains', 'value' => 'TC', 'user_filter' => 'restricted']]], 'A restricted filter on "model" must use eq or in.'],
            'two restricted filters on one field' => [['filters' => [
                ['column' => 'status', 'operator' => 'eq', 'value' => 'active', 'user_filter' => 'restricted'],
                ['column' => 'status', 'operator' => 'in', 'value' => ['inactive'], 'user_filter' => 'restricted'],
            ]], '"status" can have only one restricted filter.'],
            'invalid date' => [['filters' => [['column' => 'created_at', 'operator' => 'gt', 'value' => '2024-13-01', 'user_filter' => 'locked']]], 'The filter on "created_at" needs dates in the Y-m-d format.'],
        ];
    }

    private function storedView(): CustomViewModel
    {
        return CustomViewModel::query()->where(column: 'title', operator: 'Zebra scanners')->sole();
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
                ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra', 'user_filter' => 'locked'],
                ['column' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-06-30'], 'user_filter' => 'locked'],
            ],
            'is_public' => true,
        ];
    }
}
