<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database\Connection;

use Closure;
use Codercms\LaravelPgConnPool\Database\PooledDatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;

/**
 * Lazy connection is a decorator for working with connection pool like it is a single connection
 * Lazy connection is created on each unique Coroutine
 */
class LazyConnection extends Connection
{
    private PooledDatabaseManager $manager;
    private string $connectionName;
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

    private function getConnection(): Connection
    {
        if (null === $this->connection) {
            return $this->connection = $this->manager->getPooledConnection($this->connectionName);
        }

        return $this->connection;
    }

    private function returnConnection(): void
    {
        if (null === $this->connection) {
            return;
        }

        // do not return the connection if running inside transaction
        if ($this->connection->transactions > 0) {
            return;
        }

        $this->manager->returnPooledConnection($this->connection);
        $this->connection = null;

        $this->manager->returnLazyConnection($this);
    }

    private function forEachConnection(Closure $closure): void
    {
        $pool = $this->manager->getPool($this->connectionName);
        foreach ($pool->getRawConnections() as $connection) {
            $closure($connection);
        }
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

        $this->events = $events;

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

        $this->events = null;
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

        return $this->pretending;
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
