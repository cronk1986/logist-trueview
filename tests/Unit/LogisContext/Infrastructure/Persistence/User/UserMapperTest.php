<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Infrastructure\Persistence\User;

use Logis\LogisContext\Infrastructure\Persistence\User\UserMapper;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;

final class UserMapperTest extends TestCase
{
    public function test_it_maps_a_user_model_to_the_domain_user(): void
    {
        $model = new UserModel();
        $model->setRawAttributes(attributes: ['id' => 7, 'name' => 'Demo', 'email' => 'demo@logis.test', 'password' => 'hash']);

        $user = (new UserMapper())->toDomain(model: $model);

        $this->assertSame(7, $user->id()->value());
        $this->assertSame('Demo', $user->name());
        $this->assertSame('demo@logis.test', $user->email()->value());
        $this->assertSame('hash', $user->passwordHash());
    }
}
