<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\CustomView;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Logis\LogisContext\Application\Exception\CustomView\ViewExecutionTimedOutException;
use Logis\LogisContext\Infrastructure\CustomView\StatementTimeout;
use Tests\TestCase;

final class StatementTimeoutTest extends TestCase
{
    private const HEAVY_QUERY = 'SELECT COUNT(*) AS total FROM information_schema.columns a, information_schema.columns b, information_schema.columns c';

    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->app->make(abstract: ConnectionInterface::class);
    }

    public function test_it_returns_the_result_and_restores_the_previous_limit(): void
    {
        $timeout = new StatementTimeout(connection: $this->connection, milliseconds: 5000);
        $previous = $timeout->currentLimit();

        $result = $timeout->run(callback: fn (): int => $timeout->currentLimit());

        $this->assertSame(5000, $result);
        $this->assertSame($previous, $timeout->currentLimit());
    }

    public function test_a_query_over_the_limit_times_out_and_the_previous_limit_is_restored(): void
    {
        $timeout = new StatementTimeout(connection: $this->connection, milliseconds: 1);
        $previous = $timeout->currentLimit();

        try {
            $timeout->run(callback: fn () => $this->connection->select(query: self::HEAVY_QUERY));
            $this->fail('The query was expected to time out.');
        } catch (ViewExecutionTimedOutException $exception) {
            $this->assertSame('The view took longer than 1 ms to run. Add filters to narrow it down.', $exception->getMessage());
        }

        $this->assertSame($previous, $timeout->currentLimit());
    }

    public function test_other_query_errors_are_not_turned_into_timeouts(): void
    {
        $timeout = new StatementTimeout(connection: $this->connection, milliseconds: 5000);

        $this->expectException(QueryException::class);

        $timeout->run(callback: fn () => $this->connection->select(query: 'SELECT * FROM a_table_that_does_not_exist'));
    }
}
