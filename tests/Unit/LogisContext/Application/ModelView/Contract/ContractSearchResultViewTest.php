<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Contract;

use Logis\LogisContext\Application\ModelView\Contract\ContractSearchResultView;
use PHPUnit\Framework\TestCase;

final class ContractSearchResultViewTest extends TestCase
{
    public function test_it_renders_the_search_fields_of_a_contract(): void
    {
        $view = ContractSearchResultView::from(id: 4, name: 'TrueSupport 2024', status: 'active');

        $this->assertSame(['id' => 4, 'name' => 'TrueSupport 2024', 'status' => 'active'], $view->toArray());
    }
}
