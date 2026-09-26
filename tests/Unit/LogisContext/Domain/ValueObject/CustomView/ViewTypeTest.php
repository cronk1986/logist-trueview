<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\ValueObject\CustomView;

use Logis\LogisContext\Domain\Exception\CustomView\InvalidViewTypeException;
use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;

final class ViewTypeTest extends TestCase
{
    public function test_it_is_built_from_its_value(): void
    {
        $this->assertSame(ViewType::MyTickets, ViewType::fromValue(value: 'my_tickets'));
    }

    public function test_an_unknown_value_is_rejected(): void
    {
        $this->expectException(InvalidViewTypeException::class);
        $this->expectExceptionMessage('"my_invoices" is not a view type.');

        ViewType::fromValue(value: 'my_invoices');
    }
}
