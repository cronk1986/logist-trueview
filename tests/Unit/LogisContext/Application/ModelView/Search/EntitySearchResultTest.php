<?php

declare(strict_types=1);

namespace Tests\Unit\LogisContext\Application\ModelView\Search;

use Logis\LogisContext\Application\ModelView\Search\EntitySearchResult;
use PHPUnit\Framework\TestCase;

final class EntitySearchResultTest extends TestCase
{
    public function test_an_empty_result_has_no_rows_and_no_total(): void
    {
        $result = EntitySearchResult::empty();

        $this->assertSame([], $result->results());
        $this->assertSame(0, $result->total());
    }

    public function test_it_survives_a_round_trip_through_an_array(): void
    {
        $result = EntitySearchResult::from(results: [['id' => 1, 'subject' => 'Jam']], total: 9);

        $restored = EntitySearchResult::fromArray(values: $result->toArray());

        $this->assertSame([['id' => 1, 'subject' => 'Jam']], $restored->results());
        $this->assertSame(9, $restored->total());
    }
}
