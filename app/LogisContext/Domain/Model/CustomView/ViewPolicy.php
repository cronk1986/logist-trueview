<?php

declare(strict_types=1);

namespace Logis\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\Model\User\User;

final class ViewPolicy
{
    public function canSee(User $user, CustomView $view): bool
    {
        if ($view->isOwnedBy(user: $user)) {
            return true;
        }

        if (!$view->isPublic()) {
            return false;
        }

        $customer = $user->customer();

        if ($customer === null) {
            return false;
        }

        return $customer->isSameAs(other: $view->customer());
    }

    public function canManage(User $user, CustomView $view): bool
    {
        return $view->isOwnedBy(user: $user);
    }
}
