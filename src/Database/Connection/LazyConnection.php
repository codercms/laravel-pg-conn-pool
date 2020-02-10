<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database\Connection;

use Closure;
use Codercms\LaravelPgConnPool\Database\PooledDatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Schema\Grammars\Grammar as SchemaGrammar;

/**
 * Lazy connection is a decorator for working with connection pool like it is a single connection
 * Lazy connection is created on each unique Coroutine
 */
class LazyConnection extends Connection
{
    private PooledDatabaseManager $manager;
    private string $connectionName;
    private int $cid;
    private ?Connection $connection = null;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     *
     * @param PooledDatabaseManager $manager
     * @param string $connectionName
     */
    public function __construct(PooledDatabaseManager $manager, string $connectionName)
    {
        $this->manager = $manager;
        $this->connectionName = $connectionName;
    }

    public function __destruct()
    {
        $this->returnConnection();
    }

    public function getName(): string
    {
        return $this->connectionName;
    }

    public function setCid(int $cid): void
    {
        $this->cid = $cid;
    }

    public function getCid(): int
    {
        return $this->cid;
    }

    /**
     * Get the connection from the pool
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        if (null === $this->connection) {
            return $this->connection = $this->manager->getPooledConnection($this->connectionName);
        }

        return $this->connection;
    }

    /**
     * Return taken connection to the pool
     */
    public function returnConnection(): void
    {
        if (null === $this->connection) {
            return;
        }

        // do not return the connection if running inside transaction
        if ($this->connection->transactions > 0) {
            return;
        }

        $connection = $this->connection;
        $this->connection = null;

        $this->manager->forgetLazyConnection($this);
        $this->manager->returnPooledConnection($connection);
    }

    private function forEachConnection(Closure $closure): void
    {
        $pool = $this->manager->getPool($this->connectionName);
        foreach ($pool->getRawConnections() as $connection) {
            $closure($connection);
        }
    }

    /**
     * Get the current PDO connection.
     *
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->getConnection()->getPdo();
    }

    /**
     * Get the current PDO connection parameter without executing any reconnect logic.
     *
     * @return \PDO|\Closure|null
     */
    public function getRawPdo()
    {
        return $this->getConnection()->getRawPdo();
    }

    /**
     * Get the current PDO connection used for reading.
     *
     * @return \PDO
     */
    public function getReadPdo()
    {
        return $this->getConnection()->getReadPdo();
    }

    /**
     * Get the current read PDO connection parameter without executing any reconnect logic.
     *
     * @return \PDO|\Closure|null
     */
    public function getRawReadPdo(): \PDO
    {
        return $this->getConnection()->getRawReadPdo();
    }

    /**
     * Set the PDO connection.
     *
     * @param  \PDO|\Closure|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        if (null !== $this->connection) {
            $this->connection->setPdo($pdo);
        }

        return $this;
    }

    /**
     * Set the PDO connection used for reading.
     *
     * @param  \PDO|\Closure|null  $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        if (null !== $this->connection) {
            $this->connection->setReadPdo($pdo);
        }

        return $this;
    }

    /**
     * Get the Doctrine DBAL database connection instance.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDoctrineConnection()
    {
        return $this->getConnection()->getDoctrineConnection();
    }

    /**
     * Set the query grammar used by the connection.
     *
     * @param  \Illuminate\Database\Query\Grammars\Grammar  $grammar
     * @return $this
     */
    public function setQueryGrammar(QueryGrammar $grammar)
    {
        $this->forEachConnection(function (Connection $connection) use ($grammar) {
            $connection->setQueryGrammar($grammar);
        });

        parent::setQueryGrammar($grammar);

        return $this;
    }

    /**
     * Set the schema grammar used by the connection.
     *
     * @param  \Illuminate\Database\Schema\Grammars\Grammar  $grammar
     * @return $this
     */
    public function setSchemaGrammar(SchemaGrammar $grammar)
    {
        $this->forEachConnection(function (Connection $connection) use ($grammar) {
            $connection->setSchemaGrammar($grammar);
        });

        parent::setSchemaGrammar($grammar);

        return $this;
    }

    /**
     * Set the query post processor used by the connection.
     *
     * @param  \Illuminate\Database\Query\Processors\Processor  $processor
     * @return $this
     */
    public function setPostProcessor(Processor $processor)
    {
        $this->forEachConnection(function (Connection $connection) use ($processor) {
            $connection->setPostProcessor($processor);
        });

        parent::setPostProcessor($processor);

        return $this;
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        if (null !== $this->connection) {
            $this->connection->disconnect();
        }
    }

    /**
     * Reconnect to the database.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect()
    {
        if (null !== $this->connection) {
            $this->connection->reconnect();
        }
    }

    /**
     * Set the reconnect instance on the connection.
     *
     * @param  callable  $reconnector
     * @return $this
     */
    public function setReconnector(callable $reconnector)
    {
        $this->forEachConnection(function (Connection $connection) use ($reconnector) {
            $connection->setReconnector($reconnector);
        });

        return $this;
    }

    /**
     * Set the event dispatcher instance on the connection.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return $this
     */
    public function setEventDispatcher(Dispatcher $events)
    {
        $this->forEachConnection(function (Connection $connection) use ($events) {
            $connection->setEventDispatcher($events);
        });

        parent::setEventDispatcher($events);

        return $this;
    }

    /**
     * Unset the event dispatcher for this connection.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->forEachConnection(function (Connection $connection) {
            $connection->unsetEventDispatcher();
        });

        parent::unsetEventDispatcher();
    }

    /**
     * Enable the query log on the connection.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->forEachConnection(function (Connection $connection) {
            $connection->enableQueryLog();
        });

        parent::enableQueryLog();
    }

    /**
     * Disable the query log on the connection.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->forEachConnection(function (Connection $connection) {
            $connection->disableQueryLog();
        });

        parent::disableQueryLog();
    }

    /**
     * Get the connection query log.
     *
     * @return array
     */
    public function getQueryLog()
    {
        $log = [];

        $this->forEachConnection(function (Connection $connection) use (&$log) {
            $log = \array_merge($log, $connection->getQueryLog());
        });

        return $log;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->forEachConnection(function (Connection $connection) {
            $connection->flushQueryLog();
        });
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        $connection = $this->getConnection();

        try {
            return $connection->pretend($callback);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Determine if the connection is in a "dry run".
     *
     * @return bool
     */
    public function pretending()
    {
        if (null !== $this->connection) {
            return $this->connection->pretending();
        }

        return parent::pretending();
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $connection = $this->getConnection();

        try {
            yield from $connection->cursor($query, $bindings, $useReadPdo);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        $connection = $this->getConnection();

        try {
            return $connection->select($query, $bindings, $useReadPdo);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        $connection = $this->getConnection();

        try {
            return $connection->insert($query, $bindings);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run an update statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function update($query, $bindings = [])
    {
        $connection = $this->getConnection();

        try {
            return $connection->update($query, $bindings);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run a delete statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function delete($query, $bindings = [])
    {
        $connection = $this->getConnection();

        try {
            return $connection->delete($query, $bindings);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        $connection = $this->getConnection();

        try {
            return $connection->statement($query, $bindings);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        $connection = $this->getConnection();

        try {
            return $connection->affectingStatement($query, $bindings);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        $connection = $this->getConnection();

        try {
            return $connection->unprepared($query);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws \Exception|\Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        $connection = $this->getConnection();

        try {
            return $connection->transaction($callback, $attempts);
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function beginTransaction()
    {
        $connection = $this->getConnection();

        try {
            $connection->beginTransaction();
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        $connection = $this->getConnection();

        try {
            $connection->commit();
        } finally {
            $this->returnConnection();
        }
    }

    /**
     * Rollback the active database transaction.
     *
     * @param null $toLevel
     * @return void
     * @throws \Exception
     */
    public function rollBack($toLevel = null)
    {
        $connection = $this->getConnection();

        try {
            $connection->rollBack($toLevel);
        } finally {
            $this->returnConnection();
        }
    }
}
