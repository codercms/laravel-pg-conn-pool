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
     * @var Pool[]
     */
    private array $pools = [];

    /**
     * First level key - connection name
     * Second level key - coroutine id (cid)
     *
     * Caching of taken connections is needed to prevent pool drying when using EloquentORM
     *
     * @var LazyConnection[][]
     */
    private array $takenConnections = [];

    /**
     * Cache of configured LazyConnections used for
     *
     * @var LazyConnection[]
     */
    private array $cachedLazyConnections = [];

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

        // if lazy connection is already created for the current coroutine there is no need to create new one
        $lazyConnection = $this->takenConnections[$name][$cid] ?? null;
        if (null !== $lazyConnection) {
            return $lazyConnection;
        }

        return $this->takenConnections[$name][$cid] = clone $this->cachedLazyConnections[$name];
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

        // forget lazy connections
        $this->takenConnections[$name] = [];

        // forget pool and connections (for backward compatibility)
        unset($this->pools[$name], $this->connections[$name]);
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
     * @param LazyConnection $lazyConnection
     */
    public function returnLazyConnection(LazyConnection $lazyConnection): void
    {
        $name = $lazyConnection->getName();
        $cid = \Swoole\Coroutine::getCid();

        unset($this->takenConnections[$name][$cid]);
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
     * Free all taken connections by coroutine
     */
    public function freeTakenConnections(): void
    {
        $cid = \Swoole\Coroutine::getCid();

        foreach ($this->takenConnections as $name => $connections) {
            unset($this->takenConnections[$name][$cid]);
        }
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
}
