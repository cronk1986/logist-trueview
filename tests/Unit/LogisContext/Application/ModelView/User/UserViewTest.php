<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\User;

use Logis\LogisContext\Application\ModelView\User\UserView;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class UserViewTest extends TestCase
{
    public function test_it_exposes_the_public_user_data(): void
    {
        $view = UserView::from(user: UserMother::create(id: 2, name: 'Ada', email: 'ada@logis.test'));

        $this->assertSame(['id' => 2, 'name' => 'Ada', 'email' => 'ada@logis.test'], $view->toArray());
    }
}
