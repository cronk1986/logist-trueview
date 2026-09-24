<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\GetUser;

use Logis\LogisContext\Application\ModelView\User\UserView;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class GetUserHandler
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function handle(GetUser $query): QueryResponse
    {
        $user = $this->users->findById(id: UserId::fromInt(value: $query->userId));

        if ($user === null) {
            throw UserNotFoundException::withId(id: $query->userId);
        }

        return QueryResponse::from(data: UserView::from(user: $user)->toArray());
    }
}
