<?php

declare(strict_types=1);

namespace Tests\Unit\SharedContext\Infrastructure\Persistence;

use Logis\SharedContext\Infrastructure\Persistence\EagerLoad;
use PHPUnit\Framework\TestCase;

final class EagerLoadTest extends TestCase
{
    public function test_it_prefixes_every_nested_relation(): void
    {
        $this->assertSame(['customer.parent', 'customer.owner'], EagerLoad::nested(relation: 'customer', relations: ['parent', 'owner']));
    }

    public function test_a_relation_without_nested_relations_is_loaded_alone(): void
    {
        $this->assertSame(['status'], EagerLoad::nested(relation: 'status', relations: []));
    }
}
