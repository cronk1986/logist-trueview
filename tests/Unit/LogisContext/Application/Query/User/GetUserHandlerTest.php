<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\Query\User;

use Logis\LogisContext\Application\Query\User\GetUser;
use Logis\LogisContext\Application\Query\User\GetUserHandler;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\User\InMemoryUserRepository;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class GetUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private GetUserHandler $handler;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->handler = new GetUserHandler(users: $this->users);
    }

    public function test_it_returns_the_user_view(): void
    {
        $this->users->add(user: UserMother::create(id: 3, name: 'Ada', email: 'ada@logis.test'));

        $response = $this->handler->handle(query: new GetUser(userId: 3));

        $this->assertSame(['id' => 3, 'name' => 'Ada', 'email' => 'ada@logis.test'], $response->data());
    }

    public function test_an_unknown_user_is_not_found(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->handler->handle(query: new GetUser(userId: 99));
    }
}
