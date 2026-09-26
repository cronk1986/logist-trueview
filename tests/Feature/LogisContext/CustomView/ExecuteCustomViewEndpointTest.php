<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\CustomView;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Logis\LogisContext\Application\Exception\CustomView\ViewExecutionTimedOutException;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilder;
use Logis\LogisContext\Application\Services\CustomView\ViewBuilders;
use Logis\LogisContext\Application\Services\CustomView\ViewExecutionCriteria;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\StatementTimeout;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\CountsQueries;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\InMemoryViewBuilder;

final class ExecuteCustomViewEndpointTest extends TestCase
{
    use CountsQueries;
    use InteractsWithCustomViews;
    use RefreshDatabase;

    private CustomerModel $parent;
    private CustomerModel $subAccount;
    private UserModel $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = CustomerModel::factory()->create();
        $this->subAccount = CustomerModel::factory()->subAccountOf(parent: $this->parent)->create();
        $this->owner = $this->userOf(customer: $this->parent, email: 'owner@logis.test');
    }

    public function test_the_registry_resolves_a_builder_for_every_view_type(): void
    {
        $builders = $this->app->make(abstract: ViewBuilders::class);

        foreach (ViewType::cases() as $type) {
            $this->assertSame($type, $builders->for(type: $type)->type());
        }
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets);

        $this->postJson(uri: '/api/v1/views/' . $view->id . '/execute')->assertUnauthorized();
    }

    public function test_it_returns_the_first_page_of_an_asset_view(): void
    {
        $zebra = AssetModel::factory()->create(attributes: ['customer_id' => $this->parent->id, 'manufacturer' => 'Zebra', 'serial_number' => 'ZB-1']);
        AssetModel::factory()->create(attributes: ['customer_id' => $this->parent->id, 'manufacturer' => 'Honeywell']);
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, columns: ['serial_number', 'manufacturer'], filters: [['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']]);

        $this->execute(user: $this->owner, view: $view)
            ->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'message', expect: 'View results')
            ->assertJsonPath(path: 'data.view', expect: ['id' => $view->id, 'title' => $view->title, 'view_type' => 'my_assets'])
            ->assertJsonPath(path: 'data.columns', expect: ['serial_number', 'manufacturer'])
            ->assertJsonPath(path: 'data.rows', expect: [['id' => $zebra->id, 'serial_number' => 'ZB-1', 'manufacturer' => 'Zebra']])
            ->assertJsonPath(path: 'meta.limit', expect: 50)
            ->assertJsonPath(path: 'meta.next_cursor', expect: null)
            ->assertJsonPath(path: 'meta.has_more', expect: false)
            ->assertJsonStructure(structure: ['meta' => ['timestamp', 'version', 'execution_time_ms']]);
    }

    public function test_it_executes_a_view_of_every_type(): void
    {
        TicketModel::factory()->create(attributes: ['customer_id' => $this->parent->id]);
        ContractModel::factory()->create(attributes: ['customer_id' => $this->parent->id]);
        LocationModel::factory()->create(attributes: ['customer_id' => $this->parent->id]);

        foreach ([ViewType::MyTickets, ViewType::MyContracts, ViewType::MyLocations] as $type) {
            $this->execute(user: $this->owner, view: $this->customView(owner: $this->owner, type: $type, filters: []))
                ->assertOk()
                ->assertJsonCount(count: 1, key: 'data.rows');
        }
    }

    public function test_a_public_view_returns_the_rows_of_the_user_who_executes_it(): void
    {
        $ownerAsset = AssetModel::factory()->create(attributes: ['customer_id' => $this->parent->id]);
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, filters: [], isPublic: true);
        $colleague = $this->userOf(customer: $this->parent, email: 'colleague@logis.test', canViewSubAccounts: true);
        $subAccountAsset = AssetModel::factory()->create(attributes: ['customer_id' => $this->subAccount->id]);

        $this->assertSame([$ownerAsset->id], $this->rowIds(user: $this->owner, view: $view));
        $this->assertSame([$subAccountAsset->id, $ownerAsset->id], $this->rowIds(user: $colleague, view: $view));
    }

    public function test_sub_accounts_are_included_only_with_the_permission(): void
    {
        $own = AssetModel::factory()->create(attributes: ['customer_id' => $this->parent->id]);
        $sub = AssetModel::factory()->create(attributes: ['customer_id' => $this->subAccount->id]);
        AssetModel::factory()->create();
        $withPermission = $this->userOf(customer: $this->parent, email: 'manager@logis.test', canViewSubAccounts: true);

        $this->assertSame([$own->id], $this->rowIds(user: $this->owner, view: $this->customView(owner: $this->owner, type: ViewType::MyAssets, filters: [])));
        $this->assertSame([$sub->id, $own->id], $this->rowIds(user: $withPermission, view: $this->customView(owner: $withPermission, type: ViewType::MyAssets, filters: [])));
    }

    public function test_a_view_of_another_customer_is_not_found(): void
    {
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, isPublic: true);
        $stranger = $this->userOf(customer: CustomerModel::factory()->create(), email: 'stranger@logis.test');

        $this->execute(user: $stranger, view: $view)->assertNotFound();
    }

    public function test_it_follows_the_cursor_to_the_last_page(): void
    {
        AssetModel::factory()->count(count: 5)->create(attributes: ['customer_id' => $this->parent->id]);
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, filters: []);
        $token = $this->tokenOf(user: $this->owner);
        $seen = [];
        $body = ['limit' => 2, 'sort' => ['field' => 'serial_number', 'direction' => 'asc']];

        do {
            $response = $this->callViews(method: 'POST', token: $token, path: '/' . $view->id . '/execute', data: $body)->assertOk();
            $seen = [...$seen, ...array_column(array: (array) $response->json(key: 'data.rows'), column_key: 'id')];
            $body['cursor'] = $response->json(key: 'meta.next_cursor');
        } while ($body['cursor'] !== null);

        $this->assertCount(5, array_unique(array: $seen));
        $this->assertCount(5, $seen);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidBodies')]
    public function test_invalid_input_is_rejected(array $body, ?string $field, ?string $message): void
    {
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets);

        $response = $this->execute(user: $this->owner, view: $view, body: $body)->assertUnprocessable();

        if ($field !== null) {
            $response->assertJsonStructure(structure: ['data' => [$field]]);
        }

        if ($message !== null) {
            $response->assertJsonPath(path: 'message', expect: $message);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, ?string, ?string}>
     */
    public static function invalidBodies(): array
    {
        return [
            'limit zero' => [['limit' => 0], 'limit', null],
            'limit above the maximum' => [['limit' => 1001], 'limit', null],
            'unknown sort direction' => [['sort' => ['field' => 'id', 'direction' => 'up']], 'sort.direction', null],
            'sort without field' => [['sort' => ['direction' => 'asc']], 'sort.field', null],
            'unknown sort field' => [['sort' => ['field' => 'customer_id']], null, 'my_assets views cannot be sorted by "customer_id".'],
            'relation sort field' => [['sort' => ['field' => 'location']], null, 'my_assets views cannot be sorted by "location".'],
            'cursor too long' => [['cursor' => str_repeat(string: 'a', times: 1025)], 'cursor', null],
            'q too long' => [['q' => str_repeat(string: 'a', times: 256)], 'q', null],
            'garbage cursor' => [['cursor' => 'garbage'], null, 'The cursor is not valid. Use the next_cursor of the previous page.'],
        ];
    }

    public function test_a_search_term_must_be_valid_utf8(): void
    {
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets);

        $this->post(uri: '/api/v1/views/' . $view->id . '/execute', data: ['q' => "zt4\xFF"], headers: ['Accept' => 'application/json', ...$this->bearer(token: $this->tokenOf(user: $this->owner))])
            ->assertUnprocessable()
            ->assertJsonStructure(structure: ['data' => ['q']]);
    }

    public function test_a_repeated_execution_runs_no_view_queries(): void
    {
        AssetModel::factory()->count(count: 3)->create(attributes: ['customer_id' => $this->parent->id]);
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, filters: []);

        $this->assertSame(1, $this->assetQueriesOf(view: $view));
        $this->assertSame(0, $this->assetQueriesOf(view: $view));
    }

    public function test_an_execution_that_times_out_answers_504_and_is_reported(): void
    {
        Exceptions::fake();
        $timeout = new StatementTimeout(connection: $this->app->make(abstract: ConnectionInterface::class), milliseconds: 1);
        $slowAssets = new class (timeout: $timeout) implements ViewBuilder {
            public function __construct(private readonly StatementTimeout $timeout)
            {
            }

            public function type(): ViewType
            {
                return ViewType::MyAssets;
            }

            public function execute(ViewExecutionCriteria $criteria): ViewPage
            {
                $this->timeout->run(callback: fn () => DB::select(query: 'SELECT COUNT(*) FROM information_schema.columns a, information_schema.columns b, information_schema.columns c'));

                return ViewPage::empty();
            }
        };
        $others = array_map(
            callback: fn (ViewType $type): ViewBuilder => new InMemoryViewBuilder(type: $type, page: ViewPage::empty()),
            array: [ViewType::MyTickets, ViewType::MyContracts, ViewType::MyLocations],
        );
        $this->app->instance(abstract: ViewBuilders::class, instance: new ViewBuilders(builders: [$slowAssets, ...$others]));
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, filters: []);

        $this->execute(user: $this->owner, view: $view)
            ->assertStatus(status: 504)
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'The view took longer than 1 ms to run. Add filters to narrow it down.');

        Exceptions::assertReported(ViewExecutionTimedOutException::class);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_rows(): void
    {
        $view = $this->customView(owner: $this->owner, type: ViewType::MyAssets, columns: ['serial_number', 'location', 'contract'], filters: []);
        $execute = fn (string $token) => fn () => $this->callViews(method: 'POST', token: $token, path: '/' . $view->id . '/execute')->assertOk();

        AssetModel::factory()->withLocation()->withContract()->create(attributes: ['customer_id' => $this->parent->id]);
        $withOne = $this->countQueries(callback: $execute($this->tokenOf(user: $this->owner)));

        AssetModel::factory()->count(count: 30)->withLocation()->withContract()->create(attributes: ['customer_id' => $this->parent->id]);
        Cache::flush();

        $this->assertSame($withOne, $this->countQueries(callback: $execute($this->tokenOf(user: $this->owner))));
    }

    /**
     * @param list<string> $columns
     * @param list<array<string, mixed>>|null $filters
     */
    private function customView(UserModel $owner, ViewType $type, ?array $columns = null, ?array $filters = null, bool $isPublic = false): CustomViewModel
    {
        $attributes = ['user_id' => $owner->id, 'is_public' => $isPublic];

        if ($columns !== null) {
            $attributes['columns'] = $columns;
        }

        if ($filters !== null) {
            $attributes['filters'] = $filters;
        }

        return CustomViewModel::factory()->ofType(type: $type)->create(attributes: $attributes);
    }

    /**
     * @param array<string, mixed> $body
     * @return TestResponse<Response>
     */
    private function execute(UserModel $user, CustomViewModel $view, array $body = []): TestResponse
    {
        return $this->callViews(method: 'POST', token: $this->tokenOf(user: $user), path: '/' . $view->id . '/execute', data: $body);
    }

    /**
     * @return list<mixed>
     */
    private function rowIds(UserModel $user, CustomViewModel $view): array
    {
        return array_column(array: (array) $this->execute(user: $user, view: $view)->json(key: 'data.rows'), column_key: 'id');
    }

    private function assetQueriesOf(CustomViewModel $view): int
    {
        $token = $this->tokenOf(user: $this->owner);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->callViews(method: 'POST', token: $token, path: '/' . $view->id . '/execute')->assertOk();
        $queries = array_filter(array: DB::getQueryLog(), callback: fn (array $query): bool => str_contains(haystack: $query['query'], needle: 'from `assets`'));
        DB::disableQueryLog();

        return count(value: $queries);
    }
}
