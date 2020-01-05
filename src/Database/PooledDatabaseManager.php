<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;

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
     * @var PooledConnectionInterface[][]|Connection[][]
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

    public function getPool(?string $connectionName): Pool
    {
        $connName = $connectionName ?? $this->getDefaultConnection();

        $this->initPool($connName);

        if (!isset($this->pools[$connectionName])) {
            throw new \InvalidArgumentException("There is no pool for {$connectionName} connection");
        }

        return $this->pools[$connectionName];
    }

    /**
     * @param PooledConnectionInterface|Connection $connection
     */
    public function returnConnection(PooledConnectionInterface $connection): void
    {
        $connName = $connection->getName();

        $pool = $this->pools[$connName] ?? null;
        if (null === $pool) {
            return;
        }

        $this->removeTakenConnection($connection);
        $pool->push($connection);
    }

    /**
     * Free all taken connections by coroutine
     */
    public function freeTakenConnections(): void
    {
        $cid = \Swoole\Coroutine::getCid();

        foreach ($this->takenConnections as $connName => $connections) {
            $connection = $connections[$cid] ?? null;
            if (null !== $connection) {
                $this->returnConnection($connection);
            }
        }
    }

    /**
     * Get a database connection instance.
     * For each coroutine new connection will be taken from the pool.
     */
    public function connection($name = null): Connection
    {
        $cid = \Swoole\Coroutine::getCid();

        if (-1 === $cid) {
            return parent::connection($name);
        }

        $connName = $name ?? $this->getDefaultConnection();
        $this->initPool($connName);

        if (!isset($this->pools[$connName])) {
            return parent::connection($name);
        }

        if (isset($this->takenConnections[$connName][$cid])) {
            return $this->takenConnections[$connName][$cid];
        }

        return $this->takenConnections[$connName][$cid] = $this->pools[$connName]->get();
    }

    /**
     * Disconnect from the given database.
     */
    public function disconnect($name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->pools[$name])) {
            parent::disconnect($name);
            return;
        }

        $pool = $this->pools[$name];
        $pool->close();

        unset($this->pools[$name], $this->connections[$name]);
        $this->takenConnections[$name] = [];
    }

    /**
     * Disconnect pooled connection from the given database.
     *
     * @param PooledConnectionInterface|Connection $connection
     */
    public function disconnectPooled(PooledConnectionInterface $connection): void
    {
        $connection->disconnect();
    }

    /**
     * Reconnect to the database given pooled connection.
     *
     * @param PooledConnectionInterface|Connection $connection
     * @return PooledConnectionInterface
     */
    public function reconnectPooled(PooledConnectionInterface $connection): PooledConnectionInterface
    {
        $this->disconnectPooled($connection);
        $fresh = $this->makeConnection($connection->getName());

        return $connection
            ->setPdo($fresh->getPdo())
            ->setReadPdo($fresh->getReadPdo());
    }

    private function initPool(string $connName): void
    {
        if (isset($this->pools[$connName])) {
            return;
        }

        $config = $this->configuration($connName);
        if (!isset($config['poolSize'])) {
            return;
        }

        $this->pools[$connName] = $pool = new Pool($config['poolSize']);

        [$database, $type] = $this->parseConnectionName($connName);

        for ($i = 0; $i < $config['poolSize']; $i++) {
            $pool->push(
                $this->configure(
                    $this->makeConnection($database),
                    $type
                )
            );
        }
    }

    /**
     * @param PooledConnectionInterface $connection
     */
    private function removeTakenConnection(PooledConnectionInterface $connection): void
    {
        /* @var Connection $connection */
        $connName = $connection->getName();

        foreach ($this->takenConnections[$connName] as $cid => $takenConnection) {
            if ($takenConnection->getId() === $connection->getId()) {
                unset($this->takenConnections[$connName][$cid]);
                break;
            }
        }
    }
}
