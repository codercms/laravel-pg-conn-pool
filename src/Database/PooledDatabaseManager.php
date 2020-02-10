<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Codercms\LaravelPgConnPool\Database\Connection\LazyConnection;
use Codercms\LaravelPgConnPool\Database\Connection\PooledConnectionInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;

class PooledDatabaseManager extends DatabaseManager
{
    /**
     * Connection pool list (key = pool name, value = pool object)
     *
     * @var Pool[]
     */
    private array $pools = [];

    /**
     * Cache of configured LazyConnections that are used for the "connection" method
     *
     * @var LazyConnection[]
     */
    private array $cachedLazyConnections = [];

    /**
     * Taken lazy connections cache
     * It's needed because the Eloquent ORM calling the "connection" method multiple times
     * instead of caching connection object
     *
     * Cache is stored in format like: [$name][$cid]
     * Where $name is connection name, cid is coroutine id
     *
     * @var LazyConnection[][]
     */
    private array $takenConnections = [];

    public function __construct($app, ConnectionFactory $factory)
    {
        parent::__construct($app, $factory);

        $this->reconnector = function (Connection $connection) {
            if ($connection instanceof PooledConnectionInterface) {
                $this->reconnectPooled($connection);
                return;
            }

            $this->reconnect($connection->getName());
        };
    }

    /**
     * Get a database connection instance.
     * For each coroutine new LazyConnection will be returned
     *
     * @param string|null $name
     * @return Connection|LazyConnection
     */
    public function connection($name = null): Connection
    {
        $cid = \Swoole\Coroutine::getCid();

        if (-1 === $cid) {
            return parent::connection($name);
        }

        $name = $name ?? $this->getDefaultConnection();
        $this->initPool($name);

        // if pool is not configured for the given connection name just fallback to the default logic
        if (!isset($this->pools[$name])) {
            return parent::connection($name);
        }

        // Get a connection that is already in use by the current coroutine
        $lazyConnection = $this->takenConnections[$name][$cid] ?? null;
        // if coroutine has not used lazy connection, let's create new one
        if (null === $lazyConnection) {
            $lazyConnection = clone $this->cachedLazyConnections[$name];
            $lazyConnection->setCid($cid);

            // remember a lazy connection for the current coroutine
            $this->takenConnections[$name][$cid] = $lazyConnection;
        }

        return $lazyConnection;
    }

    /**
     * Disconnect from the given database.
     *
     * @param string|null $name
     */
    public function disconnect($name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        $pool = $this->pools[$name] ?? null;

        // if pool is not configured for the given connection name just fallback to the default logic
        if (null === $pool) {
            parent::disconnect($name);

            return;
        }

        // forget pool
        unset($this->pools[$name]);
    }

    /**
     * Reconnect to the database given pooled connection.
     *
     * @param PooledConnectionInterface|Connection $connection
     * @return PooledConnectionInterface|Connection
     */
    public function reconnectPooled(PooledConnectionInterface $connection): PooledConnectionInterface
    {
        $connection->disconnect();
        $fresh = $this->makeConnection($connection->getName());

        return $connection
            ->setPdo($fresh->getPdo())
            ->setReadPdo($fresh->getReadPdo());
    }

    /**
     * Get connection pool
     *
     * @param string|null $name
     * @return Pool
     */
    public function getPool(?string $name = null): Pool
    {
        $name = $name ?? $this->getDefaultConnection();

        $this->initPool($name);

        if (!isset($this->pools[$name])) {
            throw new \InvalidArgumentException("There is no pool for '{$name}' connection");
        }

        return $this->pools[$name];
    }

    /**
     * @param string $name
     * @return PooledConnectionInterface|Connection
     */
    public function getPooledConnection(string $name): PooledConnectionInterface
    {
        $pool = $this->getPool($name);

        return $pool->get();
    }

    /**
     * @param PooledConnectionInterface|Connection $connection
     */
    public function returnPooledConnection(PooledConnectionInterface $connection): void
    {
        $pool = $this->pools[$connection->getName()] ?? null;
        if (null === $pool) {
            // disconnection connection if pool is already closed
            $connection->disconnect();

            return;
        }

        $pool->push($connection);
    }

    /**
     * Initializes pool with the given connection name
     *
     * @param string $name
     */
    private function initPool(string $name): void
    {
        if (isset($this->pools[$name])) {
            return;
        }

        $config = $this->configuration($name);
        if (!isset($config['poolSize'])) {
            return;
        }

        $this->pools[$name] = new Pool($config['poolSize'], function () use ($name) {
            return $this->makePooledConnection($name);
        });

        // configure lazy connection
        $this->cachedLazyConnections[$name] = $lazyConnection = new LazyConnection($this, $name);

        if ($this->app->bound('events')) {
            $lazyConnection->setEventDispatcher($this->app['events']);
        }

        $lazyConnection->setReconnector($this->reconnector);

        switch ($config['driver']) {
            case 'pgsql_pool':
                $lazyConnection->setQueryGrammar(new PostgresGrammar());
                $lazyConnection->setPostProcessor(new PostgresProcessor());
                break;
        }
    }

    /**
     * Used in the pool to create new connections
     *
     * @param string $name
     * @return Connection
     */
    private function makePooledConnection(string $name): Connection
    {
        [$database, $type] = $this->parseConnectionName($name);

        return $this->configure(
            $this->makeConnection($database),
            $type
        );
    }

    public function forgetLazyConnection(LazyConnection $connection): void
    {
        $cid = $connection->getCid();
        $name = $connection->getName();

        unset($this->takenConnections[$name][$cid]);
    }

    /**
     * Free all taken connections by coroutine
     */
    public function freeTakenConnections(): void
    {
        $cid = \Swoole\Coroutine::getCid();

        foreach ($this->takenConnections as $name => $connections) {
            unset($this->takenConnections[$name][$cid]);
        }
    }
}
