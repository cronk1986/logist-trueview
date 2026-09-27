<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\CustomView;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Logis\LogisContext\Application\Exception\CustomView\ViewExecutionTimedOutException;

/**
 * Limits how long the SELECT statements of a callback may run, with MySQL's max_execution_time.
 *
 * The limit is a session variable, so it outlives the request on a reused connection (queue workers,
 * tests). run() reads the current value first and restores it afterwards. MySQL stops a statement
 * that exceeds the limit with error 3024 (ER_QUERY_TIMEOUT), which becomes a ViewExecutionTimedOutException.
 */
final class StatementTimeout
{
    private const MAX_EXECUTION_TIME_EXCEEDED = 3024;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly int $milliseconds,
    ) {
    }

    /**
     * @template TResult
     * @param Closure(): TResult $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        $previous = $this->currentLimit();
        $this->setLimit(milliseconds: $this->milliseconds);

        try {
            return $callback();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::MAX_EXECUTION_TIME_EXCEEDED) {
                throw ViewExecutionTimedOutException::after(milliseconds: $this->milliseconds);
            }

            throw $exception;
        } finally {
            $this->setLimit(milliseconds: $previous);
        }
    }

    public function currentLimit(): int
    {
        $row = $this->connection->selectOne(query: 'SELECT @@SESSION.max_execution_time AS milliseconds');

        return (int) $row->milliseconds;
    }

    private function setLimit(int $milliseconds): void
    {
        $this->connection->statement(query: sprintf('SET SESSION max_execution_time = %d', $milliseconds));
    }
}
