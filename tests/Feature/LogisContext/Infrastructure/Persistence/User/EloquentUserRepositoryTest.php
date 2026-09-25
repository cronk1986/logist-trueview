<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\User\EloquentUserRepository;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\Feature\CountsQueries;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use CountsQueries;
    use RefreshDatabase;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentUserRepository::class);
    }

    public function test_it_maps_a_user_with_the_customer_and_its_parent(): void
    {
        $parent = CustomerModel::factory()->create();
        $customer = CustomerModel::factory()->subAccountOf(parent: $parent)->create();
        $model = UserModel::factory()->create(attributes: ['customer_id' => $customer->id, 'email' => 'demo@logis.test']);

        $user = $this->repository->findByEmail(email: Email::fromString(value: 'demo@logis.test'));

        $this->assertSame($model->id, $user?->id()->value());
        $this->assertSame($customer->id, $user->customer()?->id()->value());
        $this->assertSame($parent->id, $user->customer()->parent()?->id()->value());
    }

    public function test_it_maps_a_user_without_a_customer(): void
    {
        $model = UserModel::factory()->withoutCustomer()->create();

        $user = $this->repository->findById(id: UserId::fromInt(value: $model->id));

        $this->assertNotNull($user);
        $this->assertNull($user->customer());
    }

    public function test_a_missing_user_is_null(): void
    {
        $this->assertNull($this->repository->findById(id: UserId::fromInt(value: 999)));
    }

    public function test_it_loads_the_whole_graph_with_a_fixed_number_of_queries(): void
    {
        $parent = CustomerModel::factory()->create();
        $customer = CustomerModel::factory()->subAccountOf(parent: $parent)->create();
        $model = UserModel::factory()->create(attributes: ['customer_id' => $customer->id]);

        $queries = $this->countQueries(callback: fn () => $this->repository->findById(id: UserId::fromInt(value: $model->id)));

        $this->assertSame(3, $queries);
    }
}
