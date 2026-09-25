<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Domain\Model\CustomView;

use Logis\LogisContext\Domain\ValueObject\CustomView\ViewType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\LogisContext\Domain\Model\User\UserMother;

final class CustomViewTest extends TestCase
{
    public function test_it_holds_its_owner_customer_and_configuration(): void
    {
        $owner = UserMother::create(id: 5);

        $view = CustomViewMother::create(id: 2, user: $owner, isDefault: true, isPublic: true);

        $this->assertSame(2, $view->id()->value());
        $this->assertSame($owner, $view->user());
        $this->assertSame(1, $view->customer()->id()->value());
        $this->assertSame('Zebra scanners', $view->title()->value());
        $this->assertSame('Every Zebra device', $view->description());
        $this->assertSame(ViewType::MyAssets, $view->type());
        $this->assertSame(['serial_number', 'model'], $view->columns()->toArray());
        $this->assertSame([['column' => 'manufacturer', 'operator' => 'eq', 'value' => 'Zebra']], $view->filters()->toArray());
        $this->assertTrue($view->isDefault());
        $this->assertTrue($view->isPublic());
    }
}
