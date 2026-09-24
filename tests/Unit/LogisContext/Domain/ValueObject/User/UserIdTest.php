<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\User;

use Logis\LogisContext\Domain\Exception\User\InvalidUserIdException;
use Logis\LogisContext\Domain\ValueObject\User\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function test_it_keeps_a_positive_id(): void
    {
        $this->assertSame(7, UserId::fromInt(value: 7)->value());
    }

    public function test_it_rejects_a_non_positive_id(): void
    {
        $this->expectException(InvalidUserIdException::class);

        UserId::fromInt(value: 0);
    }

    public function test_two_ids_with_the_same_value_are_equal(): void
    {
        $this->assertTrue(UserId::fromInt(value: 3)->equals(other: UserId::fromInt(value: 3)));
        $this->assertFalse(UserId::fromInt(value: 3)->equals(other: UserId::fromInt(value: 4)));
    }
}
