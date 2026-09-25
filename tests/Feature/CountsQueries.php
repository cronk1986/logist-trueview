<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

trait CountsQueries
{
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(value: DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
