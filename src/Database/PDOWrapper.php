<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Closure;
use MakiseCo\Postgres\Contracts\Link;
use MakiseCo\Postgres\Contracts\Transaction;
use MakiseCo\Postgres\Driver\Pq\PqBufferedResultSet;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use PDO;
use RuntimeException;
use Throwable;

class PDOWrapper extends PDO
{
    use SqlErrorHelper;

    private PostgresPool $pool;
    private ?Link $connection = null;
    private ?Transaction $transaction = null;
    private int $transactionLevel = 0;

    private int $refCount = 0;
    private Closure $release;

    /**
     * @param PostgresPool $pool
     *
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(PostgresPool $pool)
    {
        $this->pool = $pool;

        $refCount = &$this->refCount;
        $this->release = function () use (&$refCount) {
            if (--$refCount === 0) {
                $this->returnConnection();
            }
        };
    }

    public function __destruct()
    {
        if ($this->transaction !== null) {
            $this->transactionLevel = 0;

            try {
                $this->transaction = null;
            } catch (Throwable $e) {
                // ignore transaction close errors
            }
        }

        $this->returnConnection();
    }

    private function returnConnection(): void
    {
        // do not return connection while in transaction
        if ($this->transaction !== null || $this->transactionLevel > 0) {
            return;
        }

        if ($this->connection !== null) {
            $connection = $this->connection;
            $this->connection = null;

            $this->pool->push($connection);
        }
    }

    private function getConnection(): Link
    {
        return $this->connection ??= $this->pool->pop();
    }

    public function isAlive(): bool
    {
        if ($this->connection === null) {
            return true;
        }

        return $this->connection->isAlive();
    }

    public function exec($statement)
    {
        $connection = $this->getConnection();

        try {
            $result = $connection->query($statement);
        } catch (Throwable $e) {
            throw self::handleQueryError($e);
        } finally {
            $this->returnConnection();
        }

        if ($result instanceof CommandResult) {
            return $result->getAffectedRowCount();
        }

        if ($result instanceof PqBufferedResultSet) {
            return $result->getNumRows();
        }

        return false;
    }

    public function prepare($statement, $options = null): PDOStatement
    {
        $connection = $this->getConnection();

        try {
            $stmt = $connection->prepare($statement);
        } catch (Throwable $e) {
            $this->returnConnection();

            throw self::handleQueryError($e);
        }

        ++$this->refCount;

        return new PDOStatement($stmt, $this->release);
    }

    public function beginTransaction(): void
    {
        if ($this->transaction !== null) {
            throw new RuntimeException('Transaction already started, use createSavepoint');
        }

        $connection = $this->getConnection();

        $this->transaction = $connection->beginTransaction();
        $this->transactionLevel++;
    }

    public function createSavepoint(string $name): void
    {
        if (null === $this->transaction) {
            throw new RuntimeException('Transaction is not started');
        }

        $this->transaction->createSavepoint($name);
        $this->transactionLevel++;
    }

    public function rollbackTo(int $level, string $name): void
    {
        if (null === $this->transaction) {
            throw new RuntimeException('Transaction is not started');
        }

        $this->transaction->rollbackTo($name);
        $this->transactionLevel = $level;
    }

    public function commit(): bool
    {
        if (null === $this->transaction) {
            throw new RuntimeException('Transaction is not started');
        }

        $this->transaction->commit();
        $this->transaction = null;
        $this->transactionLevel = 0;

        $this->returnConnection();

        return true;
    }

    public function rollBack(): bool
    {
        if (null === $this->transaction) {
            throw new RuntimeException('Transaction is not started');
        }

        $this->transaction->rollback();
        $this->transaction = null;
        $this->transactionLevel = 0;

        $this->returnConnection();

        return true;
    }
}
