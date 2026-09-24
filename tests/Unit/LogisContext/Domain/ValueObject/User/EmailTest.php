<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\User;

use Logis\LogisContext\Domain\Exception\User\InvalidEmailException;
use Logis\LogisContext\Domain\ValueObject\User\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_it_keeps_a_valid_email_without_surrounding_spaces(): void
    {
        $this->assertSame('demo@logis.test', Email::fromString(value: '  demo@logis.test ')->value());
    }

    #[DataProvider('invalidEmails')]
    public function test_it_rejects_an_invalid_email(string $value): void
    {
        $this->expectException(InvalidEmailException::class);

        Email::fromString(value: $value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'without at' => ['demo.logis.test'];
        yield 'without domain' => ['demo@'];
    }
}
