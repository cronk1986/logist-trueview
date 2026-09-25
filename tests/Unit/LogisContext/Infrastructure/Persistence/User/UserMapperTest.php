<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\Persistence\User;

use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerMapper;
use Logis\LogisContext\Infrastructure\Persistence\Customer\CustomerModel;
use Logis\LogisContext\Infrastructure\Persistence\User\UserMapper;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class UserMapperTest extends TestCase
{
    public function test_it_maps_a_user_without_a_customer(): void
    {
        $user = $this->mapper()->toDomain(model: $this->model(customer: null));

        $this->assertSame(7, $user->id()->value());
        $this->assertSame('Demo', $user->name());
        $this->assertSame('demo@logis.test', $user->email()->value());
        $this->assertSame('hash', $user->passwordHash());
        $this->assertNull($user->customer());
    }

    public function test_it_maps_the_customer_of_a_user(): void
    {
        $customer = new CustomerModel();
        $customer->setRawAttributes(attributes: ['id' => 3, 'erp_id' => 'C-1000', 'name' => 'Logis Demo', 'parent_customer_id' => null]);
        $customer->setRelation(relation: 'parent', value: null);

        $user = $this->mapper()->toDomain(model: $this->model(customer: $customer));

        $this->assertSame(3, $user->customer()?->id()->value());
        $this->assertSame('C-1000', $user->customer()->erpId()->value());
    }

    private function mapper(): UserMapper
    {
        return new UserMapper(customerMapper: new CustomerMapper());
    }

    private function model(?CustomerModel $customer): UserModel
    {
        $model = new UserModel();
        $model->setRawAttributes(attributes: ['id' => 7, 'name' => 'Demo', 'email' => 'demo@logis.test', 'password' => 'hash', 'customer_id' => $customer?->id]);
        $model->setRelation(relation: 'customer', value: $customer);

        return $model;
    }
}
