<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Services\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\CustomViewAccessDeniedException;
use Logis\LogisContext\Domain\Exception\CustomView\CustomViewNotFoundException;
use Logis\LogisContext\Domain\Exception\User\UserNotFoundException;
use Logis\LogisContext\Domain\Model\CustomView\CustomView;
use Logis\LogisContext\Domain\Model\CustomView\CustomViewRepository;
use Logis\LogisContext\Domain\Model\CustomView\ViewPolicy;
use Logis\LogisContext\Domain\Model\User\User;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Domain\ValueObject\CustomView\CustomViewId;
use Logis\LogisContext\Domain\ValueObject\User\UserId;

final class CustomViewAccess
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CustomViewRepository $views,
        private readonly ViewPolicy $policy,
    ) {
    }

    public function user(int $userId): User
    {
        $user = $this->users->findById(id: UserId::fromInt(value: $userId));

        if ($user === null) {
            throw UserNotFoundException::withId(id: $userId);
        }

        return $user;
    }

    public function viewVisibleTo(User $user, int $viewId): CustomView
    {
        $view = $this->views->findById(id: CustomViewId::fromInt(value: $viewId));

        if ($view === null || !$this->policy->canSee(user: $user, view: $view)) {
            throw CustomViewNotFoundException::withId(id: $viewId);
        }

        return $view;
    }

    public function viewManagedBy(User $user, int $viewId): CustomView
    {
        $view = $this->viewVisibleTo(user: $user, viewId: $viewId);

        if (!$this->policy->canManage(user: $user, view: $view)) {
            throw CustomViewAccessDeniedException::manage(viewId: $viewId);
        }

        return $view;
    }
}
