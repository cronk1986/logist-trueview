<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\CustomView;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\CustomViewModel;
use Logis\LogisContext\Infrastructure\Persistence\CustomView\EloquentCustomViewRepository;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentCustomViewRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentCustomViewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentCustomViewRepository::class);
    }

    public function test_it_maps_a_view_and_its_json_configuration(): void
    {
        $filters = [
            ['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra'],
            ['column' => 'status', 'operator' => 'in', 'value' => ['active', 'inactive']],
        ];
        $model = CustomViewModel::factory()->ofType(type: ViewType::MyTickets)->asDefault()->public()->create(attributes: [
            'title' => 'Open tickets',
            'description' => null,
            'columns' => ['repair_id', 'subject'],
            'filters' => $filters,
        ]);

        $view = $this->repository->findById(id: CustomViewId::fromInt(value: $model->id));

        $this->assertNotNull($view);
        $this->assertSame($model->id, $view->id()->value());
        $this->assertSame($model->user_id, $view->user()->id()->value());
        $this->assertSame($model->customer_id, $view->customer()->id()->value());
        $this->assertSame('Open tickets', $view->title()->value());
        $this->assertNull($view->description());
        $this->assertSame(ViewType::MyTickets, $view->type());
        $this->assertSame(['repair_id', 'subject'], $view->columns()->toArray());
        $this->assertSame($filters, $view->filters()->toArray());
        $this->assertTrue($view->isDefault());
        $this->assertTrue($view->isPublic());
    }

    public function test_a_missing_view_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: CustomViewId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $customer = CustomerModel::factory()->subAccountOf(parent: CustomerModel::factory()->create())->create();
        $user = UserModel::factory()->create(attributes: ['customer_id' => $customer->id]);
        $model = CustomViewModel::factory()->create(attributes: ['user_id' => $user->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: CustomViewId::fromInt(value: $model->id)));

        $this->assertSame(6, $queries);
    }
}
