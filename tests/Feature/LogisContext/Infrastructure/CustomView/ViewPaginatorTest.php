<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\CustomView;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Application\ModelView\CustomView\ViewPage;
use Logis\LogisContext\Domain\ValueObject\Asset\AssetStatus;
use Logis\LogisContext\Domain\ValueObject\Contract\ContractStatus;
use Logis\LogisContext\Domain\ValueObject\Customer\CustomerId;
use Logis\LogisContext\Domain\ValueObject\Customer\VisibleCustomers;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\CustomView\InvalidViewCursorException;
use Logis\LogisContext\Infrastructure\CustomView\ViewPaginator;
use Logis\LogisContext\Infrastructure\Persistence\Asset\AssetModel;
use Logis\LogisContext\Infrastructure\Persistence\Asset\EloquentAssetViewBuilder;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\LogisContext\Application\Services\CustomView\ViewExecutionCriteriaMother;

final class ViewPaginatorTest extends TestCase
{
    use RefreshDatabase;

    private EloquentAssetViewBuilder $builder;
    private CustomerModel $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new EloquentAssetViewBuilder(paginator: $this->app->make(abstract: ViewPaginator::class));
        $this->customer = CustomerModel::factory()->create();

        $rows = [
            ['Zebra', AssetStatus::Retired, '2024-01-03'],
            ['Honeywell', AssetStatus::Active, '2024-01-01'],
            ['Zebra', AssetStatus::Active, '2024-01-02'],
            ['Datalogic', AssetStatus::Inactive, '2024-01-02'],
            ['Honeywell', AssetStatus::Retired, '2024-01-05'],
            ['Zebra', AssetStatus::Inactive, '2024-01-04'],
            ['Datalogic', AssetStatus::Active, '2024-01-02'],
        ];

        foreach ($rows as [$manufacturer, $status, $createdAt]) {
            AssetModel::factory()->create(attributes: [
                'customer_id' => $this->customer->id,
                'manufacturer' => $manufacturer,
                'status' => $status,
                'created_at' => CarbonImmutable::parse(time: $createdAt, timezone: 'UTC'),
            ]);
        }
    }

    #[DataProvider('sorts')]
    public function test_it_pages_through_every_row_once_in_the_sort_order(?string $field, string $direction): void
    {
        $expected = $this->expectedOrder(field: $field ?? 'id', direction: $direction);
        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $this->execute(sortField: $field, sortDirection: $direction, pageSize: 3, cursor: $cursor);
            $seen = [...$seen, ...array_column(array: $page->rows(), column_key: 'id')];
            $cursor = $page->nextCursor();
            $pages++;
        } while ($cursor !== null);

        $this->assertSame($expected, $seen);
        $this->assertSame(3, $pages);
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function sorts(): array
    {
        return [
            'default' => [null, 'desc'],
            'id ascending' => ['id', 'asc'],
            'manufacturer with ties ascending' => ['manufacturer', 'asc'],
            'manufacturer with ties descending' => ['manufacturer', 'desc'],
            'status ascending' => ['status', 'asc'],
            'status descending' => ['status', 'desc'],
            'created at with ties descending' => ['created_at', 'desc'],
        ];
    }

    public function test_the_last_page_has_no_next_cursor(): void
    {
        $this->assertNotNull($this->execute(pageSize: 6)->nextCursor());
        $this->assertNull($this->execute(pageSize: 7)->nextCursor());
    }

    public function test_a_cursor_of_another_sort_is_rejected(): void
    {
        $cursor = $this->execute(sortField: 'manufacturer', sortDirection: 'asc', pageSize: 3)->nextCursor();

        $this->expectException(InvalidViewCursorException::class);
        $this->expectExceptionMessage('The cursor belongs to another sort order. Start again from the first page.');

        $this->execute(sortField: 'created_at', sortDirection: 'asc', pageSize: 3, cursor: $cursor);
    }

    public function test_a_cursor_of_the_same_field_in_the_other_direction_is_rejected(): void
    {
        $cursor = $this->execute(sortField: 'manufacturer', sortDirection: 'asc', pageSize: 3)->nextCursor();

        $this->expectException(InvalidViewCursorException::class);

        $this->execute(sortField: 'manufacturer', sortDirection: 'desc', pageSize: 3, cursor: $cursor);
    }

    public function test_a_cursor_without_its_sort_is_rejected(): void
    {
        $cursor = base64_encode(string: '{"id":3,"_pointsToNextItems":true}');

        $this->expectException(InvalidViewCursorException::class);
        $this->expectExceptionMessage('The cursor belongs to another sort order. Start again from the first page.');

        $this->execute(pageSize: 3, cursor: $cursor);
    }

    public function test_enum_values_are_declared_in_alphabetical_order_so_sorting_and_cursors_agree(): void
    {
        foreach ([AssetStatus::cases(), ContractStatus::cases()] as $cases) {
            $values = array_map(callback: fn (AssetStatus|ContractStatus $case): string => $case->value, array: $cases);
            $sorted = $values;
            sort(array: $sorted);

            $this->assertSame($sorted, $values);
        }
    }

    #[DataProvider('malformedCursors')]
    public function test_a_malformed_cursor_is_rejected(string $cursor): void
    {
        $this->expectException(InvalidViewCursorException::class);
        $this->expectExceptionMessage('The cursor is not valid. Use the next_cursor of the previous page.');

        $this->execute(pageSize: 3, cursor: $cursor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCursors(): array
    {
        return [
            'not base64' => ['%%%'],
            'not json' => [base64_encode(string: 'garbage')],
            'json scalar' => [base64_encode(string: '1')],
            'no direction' => [base64_encode(string: '{"id":3}')],
            'previous page cursor' => [base64_encode(string: '{"id":3,"_pointsToNextItems":false,"_sort":"id:desc"}')],
            'nested value' => [base64_encode(string: '{"id":[3],"_pointsToNextItems":true}')],
        ];
    }

    public function test_a_long_multibyte_sort_value_still_fits_in_the_cursor(): void
    {
        foreach (['a', 'b'] as $suffix) {
            AssetModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'model' => str_repeat(string: 'ñ', times: 254) . $suffix]);
        }

        $cursor = $this->execute(sortField: 'model', sortDirection: 'desc', pageSize: 1)->nextCursor();

        $this->assertNotNull($cursor);
        $this->assertLessThanOrEqual(4096, strlen(string: $cursor));
        $this->assertCount(1, $this->execute(sortField: 'model', sortDirection: 'desc', pageSize: 1, cursor: $cursor)->rows());
    }

    public function test_a_tampered_cursor_only_moves_the_start_within_the_visible_rows(): void
    {
        $foreign = AssetModel::factory()->create();
        $cursor = rtrim(string: strtr(string: base64_encode(string: (string) json_encode(value: ['id' => $foreign->id + 1, '_pointsToNextItems' => true, '_sort' => 'id:desc'])), from: '+/', to: '-_'), characters: '=');

        $ids = array_column(array: $this->execute(pageSize: 50, cursor: $cursor)->rows(), column_key: 'id');

        $this->assertNotContains($foreign->id, $ids);
        $this->assertCount(7, $ids);
    }

    public function test_the_search_term_matches_like_the_global_search(): void
    {
        $match = AssetModel::factory()->create(attributes: ['customer_id' => $this->customer->id, 'manufacturer' => 'Acme', 'model' => 'XZ-100%', 'serial_number' => 'SN-UNIQUE']);

        $this->assertSame([$match->id], array_column(array: $this->execute(term: 'xz-100%')->rows(), column_key: 'id'));
        $this->assertSame([$match->id], array_column(array: $this->execute(term: 'unique')->rows(), column_key: 'id'));
        $this->assertSame([], $this->execute(term: 'XZ_100')->rows());
    }

    /**
     * @return list<int>
     */
    private function expectedOrder(string $field, string $direction): array
    {
        $ids = AssetModel::query()
            ->where(column: 'customer_id', operator: $this->customer->id)
            ->orderBy($field, $direction)
            ->orderBy('id', $direction)
            ->pluck(column: 'id')
            ->all();

        return array_values(array: array_map(callback: intval(...), array: $ids));
    }

    private function execute(
        ?string $sortField = null,
        string $sortDirection = 'desc',
        int $pageSize = 50,
        ?string $cursor = null,
        ?string $term = null,
    ): ViewPage {
        return $this->builder->execute(criteria: ViewExecutionCriteriaMother::create(
            type: ViewType::MyAssets,
            customers: VisibleCustomers::ownCustomer(customer: CustomerId::fromInt(value: $this->customer->id)),
            columns: ['manufacturer', 'status', 'created_at'],
            sortField: $sortField,
            sortDirection: $sortDirection,
            pageSize: $pageSize,
            cursor: $cursor,
            term: $term,
        ));
    }
}
