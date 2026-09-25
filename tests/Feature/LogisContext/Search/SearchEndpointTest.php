<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Search;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Logis\LogisContext\Domain\ValueObject\Search\SearchableEntity;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Contract\ContractModel;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\Location\LocationModel;
use Logis\LogisContext\Infrastructure\Persistence\Ticket\TicketModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\CountsQueries;
use Tests\Feature\LogisContext\Auth\InteractsWithAccessTokens;
use Tests\TestCase;

final class SearchEndpointTest extends TestCase
{
    use CountsQueries;
    use InteractsWithAccessTokens;
    use RefreshDatabase;

    private CustomerModel $parent;
    private CustomerModel $subAccount;
    private CustomerModel $unrelated;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = CustomerModel::factory()->create();
        $this->subAccount = CustomerModel::factory()->subAccountOf(parent: $this->parent)->create();
        $this->unrelated = CustomerModel::factory()->create();
    }

    public function test_a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson(uri: '/api/v1/search?q=zebra')->assertUnauthorized();
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidQueries')]
    public function test_invalid_input_is_rejected(array $query, string $field): void
    {
        $this->search(token: $this->logInAs(customer: $this->parent), query: $query)
            ->assertUnprocessable()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonStructure(structure: ['data' => [$field]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'missing q' => [[], 'q'];
        yield 'blank q' => [['q' => '   '], 'q'];
        yield 'q as an array' => [['q' => ['zebra']], 'q'];
        yield 'limit zero' => [['q' => 'zebra', 'limit' => 0], 'limit'];
        yield 'limit above the maximum' => [['q' => 'zebra', 'limit' => 51], 'limit'];
        yield 'limit not a number' => [['q' => 'zebra', 'limit' => 'five'], 'limit'];
        yield 'unknown entity, comma-separated' => [['q' => 'zebra', 'entities' => 'tickets,users'], 'entities.1'];
        yield 'unknown entity, array' => [['q' => 'zebra', 'entities' => ['users']], 'entities.0'];
    }

    public function test_it_returns_the_matching_tickets_in_the_standard_format(): void
    {
        $ticket = $this->ticket(customer: $this->parent, subject: 'Zebra printer jams');

        $response = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => '  zebra ', 'limit' => 3]);

        $response->assertOk()
            ->assertHeader(headerName: 'X-Refreshed-Token')
            ->assertJsonPath(path: 'success', expect: true)
            ->assertJsonPath(path: 'message', expect: 'Search results')
            ->assertJsonPath(path: 'data.tickets.results.0.id', expect: $ticket->id)
            ->assertJsonPath(path: 'data.tickets.results.0.subject', expect: 'Zebra printer jams')
            ->assertJsonPath(path: 'data.tickets.total', expect: 1)
            ->assertJsonPath(path: 'data.tickets.has_more', expect: false)
            ->assertJsonPath(path: 'meta.query', expect: 'zebra')
            ->assertJsonPath(path: 'meta.limit_per_entity', expect: 3)
            ->assertJsonStructure(structure: [
                'data' => ['tickets' => ['results' => [['id', 'repair_id', 'incident_number', 'subject', 'status']], 'total', 'has_more']],
                'meta' => ['timestamp', 'version', 'query', 'limit_per_entity', 'execution_time_ms'],
            ]);
    }

    public function test_the_limit_defaults_to_five_and_has_more_reports_the_rest(): void
    {
        foreach (range(start: 1, end: 6) as $ignored) {
            $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        }

        $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra'])
            ->assertOk()
            ->assertJsonCount(count: 5, key: 'data.tickets.results')
            ->assertJsonPath(path: 'data.tickets.total', expect: 6)
            ->assertJsonPath(path: 'data.tickets.has_more', expect: true)
            ->assertJsonPath(path: 'meta.limit_per_entity', expect: 5);
    }

    public function test_both_formats_of_entities_give_the_same_response(): void
    {
        $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        $token = $this->logInAs(customer: $this->parent);

        $commaSeparated = $this->search(token: $token, query: ['q' => 'zebra', 'entities' => 'tickets, tickets'])->assertOk();
        $array = $this->search(token: $this->refreshedToken(response: $commaSeparated), query: ['q' => 'zebra', 'entities' => ['tickets', 'tickets']])->assertOk();

        $this->assertSame($commaSeparated->json(key: 'data'), $array->json(key: 'data'));
        $this->assertSame(['tickets'], array_keys(array: $array->json(key: 'data')));
    }

    public function test_every_entity_is_searched_when_none_is_requested(): void
    {
        $response = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra'])->assertOk();
        $everyEntity = array_map(callback: fn (SearchableEntity $entity): string => $entity->value, array: SearchableEntity::cases());

        $this->assertSame($everyEntity, array_keys(array: $response->json(key: 'data')));
    }

    public function test_the_entities_come_in_canonical_order_whatever_the_requested_order(): void
    {
        $asset = AssetModel::factory()->create(attributes: ['customer_id' => $this->parent->id, 'manufacturer' => 'Zebra']);
        $this->ticket(customer: $this->parent, subject: 'Zebra printer');

        $response = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra', 'entities' => 'assets,tickets'])->assertOk();

        $this->assertSame(['tickets', 'assets'], array_keys(array: $response->json(key: 'data')));
        $response->assertJsonPath(path: 'data.assets.results.0.id', expect: $asset->id)
            ->assertJsonStructure(structure: ['data' => ['assets' => ['results' => [['id', 'serial_number', 'manufacturer', 'model', 'item_name', 'status']]]]]);
    }

    public function test_it_only_returns_active_contracts(): void
    {
        $active = ContractModel::factory()->create(attributes: ['customer_id' => $this->parent->id, 'name' => 'Zebra TrueSupport']);
        ContractModel::factory()->inactive()->create(attributes: ['customer_id' => $this->parent->id, 'name' => 'Zebra Depot Repair']);

        $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra', 'entities' => 'contracts'])
            ->assertOk()
            ->assertJsonPath(path: 'data.contracts', expect: [
                'results' => [['id' => $active->id, 'name' => 'Zebra TrueSupport', 'status' => 'active']],
                'total' => 1,
                'has_more' => false,
            ]);
    }

    public function test_it_searches_locations_by_title_and_address(): void
    {
        $location = LocationModel::factory()->create(attributes: ['customer_id' => $this->parent->id, 'title' => 'Main depot', 'city' => 'Zebraville']);

        $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra', 'entities' => ['locations']])
            ->assertOk()
            ->assertJsonPath(path: 'data.locations.results.0.id', expect: $location->id)
            ->assertJsonPath(path: 'data.locations.results.0.city', expect: 'Zebraville')
            ->assertJsonStructure(structure: ['data' => ['locations' => ['results' => [['id', 'title', 'city', 'state', 'postal_code']], 'total', 'has_more']]]);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_matches(): void
    {
        $this->seedMatches(term: 'Qxalpha', count: 1);
        $this->seedMatches(term: 'Qxomega', count: 30);
        $first = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'warm-up'])->assertOk();
        $token = $this->refreshedToken(response: $first);

        $withOneMatch = $this->countQueries(callback: function () use (&$token): void {
            $token = $this->refreshedToken(response: $this->search(token: $token, query: ['q' => 'qxalpha', 'limit' => 50])->assertOk());
        });
        $withThirtyMatches = $this->countQueries(callback: function () use ($token): void {
            $this->search(token: $token, query: ['q' => 'qxomega', 'limit' => 50])
                ->assertOk()
                ->assertJsonPath(path: 'data.tickets.total', expect: 30)
                ->assertJsonPath(path: 'data.locations.total', expect: 30);
        });

        $this->assertSame($withOneMatch, $withThirtyMatches);
    }

    public function test_a_repeated_search_runs_no_search_queries(): void
    {
        $this->seedMatches(term: 'Qxalpha', count: 2);
        $first = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'qxalpha'])->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $second = $this->search(token: $this->refreshedToken(response: $first), query: ['q' => 'QXALPHA'])->assertOk();
        $searchQueries = array_filter(
            array: DB::getQueryLog(),
            callback: fn (array $query): bool => preg_match(pattern: '/from `(tickets|assets|contracts|locations)`/', subject: $query['query']) === 1,
        );
        DB::disableQueryLog();

        $this->assertSame([], $searchQueries);
        $this->assertSame($first->json(key: 'data'), $second->json(key: 'data'));
    }

    public function test_a_cached_search_is_not_shared_with_a_user_who_sees_other_customers(): void
    {
        $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra'])->assertOk();
        UserModel::factory()->create(attributes: ['customer_id' => $this->unrelated->id, 'email' => 'other@logis.test', 'password' => 'password']);

        $this->search(token: $this->logIn(email: 'other@logis.test'), query: ['q' => 'zebra'])
            ->assertOk()
            ->assertJsonPath(path: 'data.tickets.total', expect: 0);
    }

    public function test_a_user_without_the_permission_only_sees_their_own_customer(): void
    {
        $own = $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        $this->ticket(customer: $this->subAccount, subject: 'Zebra printer');
        $this->ticket(customer: $this->unrelated, subject: 'Zebra printer');

        $response = $this->search(token: $this->logInAs(customer: $this->parent), query: ['q' => 'zebra']);

        $this->assertSame([$own->id], $this->ticketIds(response: $response));
    }

    public function test_a_user_with_the_permission_also_sees_the_sub_accounts(): void
    {
        $own = $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        $ofSubAccount = $this->ticket(customer: $this->subAccount, subject: 'Zebra printer');
        $this->ticket(customer: $this->unrelated, subject: 'Zebra printer');

        $response = $this->search(token: $this->logInAs(customer: $this->parent, canViewSubAccounts: true), query: ['q' => 'zebra']);

        $this->assertSame([$ofSubAccount->id, $own->id], $this->ticketIds(response: $response));
    }

    public function test_a_sub_account_user_never_sees_its_parent(): void
    {
        $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        $own = $this->ticket(customer: $this->subAccount, subject: 'Zebra printer');

        $response = $this->search(token: $this->logInAs(customer: $this->subAccount, canViewSubAccounts: true), query: ['q' => 'zebra']);

        $this->assertSame([$own->id], $this->ticketIds(response: $response));
    }

    public function test_a_user_without_a_customer_gets_empty_results(): void
    {
        $this->ticket(customer: $this->parent, subject: 'Zebra printer');
        UserModel::factory()->withoutCustomer()->create(attributes: ['email' => 'nobody@logis.test', 'password' => 'password']);

        $this->search(token: $this->logIn(email: 'nobody@logis.test'), query: ['q' => 'zebra'])
            ->assertOk()
            ->assertJsonPath(path: 'data.tickets', expect: ['results' => [], 'total' => 0, 'has_more' => false]);
    }

    public function test_customer_ids_sent_by_the_client_are_ignored(): void
    {
        $this->ticket(customer: $this->unrelated, subject: 'Zebra printer');

        $response = $this->search(token: $this->logInAs(customer: $this->parent), query: [
            'q' => 'zebra',
            'erpCustomerId' => (string) $this->unrelated->id,
            'customer_id' => $this->unrelated->id,
        ]);

        $this->assertSame([], $this->ticketIds(response: $response));
    }

    private function logInAs(CustomerModel $customer, bool $canViewSubAccounts = false): string
    {
        UserModel::factory()->create(attributes: [
            'customer_id' => $customer->id,
            'can_view_sub_accounts' => $canViewSubAccounts,
            'email' => 'searcher@logis.test',
            'password' => 'password',
        ]);

        return $this->logIn(email: 'searcher@logis.test');
    }

    private function seedMatches(string $term, int $count): void
    {
        $customer = ['customer_id' => $this->parent->id];

        TicketModel::factory()->count(count: $count)->create(attributes: [...$customer, 'subject' => $term . ' ticket']);
        AssetModel::factory()->count(count: $count)->create(attributes: [...$customer, 'model' => $term . ' model']);
        ContractModel::factory()->count(count: $count)->create(attributes: [...$customer, 'name' => $term . ' contract']);
        LocationModel::factory()->count(count: $count)->create(attributes: [...$customer, 'title' => $term . ' depot']);
    }

    private function ticket(CustomerModel $customer, string $subject): TicketModel
    {
        return TicketModel::factory()->create(attributes: ['customer_id' => $customer->id, 'subject' => $subject]);
    }

    /**
     * @param array<string, mixed> $query
     * @return TestResponse<Response>
     */
    private function search(string $token, array $query): TestResponse
    {
        return $this->getJson(uri: '/api/v1/search?' . http_build_query(data: $query), headers: $this->bearer(token: $token));
    }

    /**
     * @param TestResponse<Response> $response
     */
    private function refreshedToken(TestResponse $response): string
    {
        return (string) $response->headers->get(key: 'X-Refreshed-Token');
    }

    /**
     * @param TestResponse<Response> $response
     * @return list<int>
     */
    private function ticketIds(TestResponse $response): array
    {
        $response->assertOk();

        return array_column(array: $response->json(key: 'data.tickets.results'), column_key: 'id');
    }
}
