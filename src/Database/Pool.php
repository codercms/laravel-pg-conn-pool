<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Closure;
use Codercms\LaravelPgConnPool\Database\Connection\PooledConnectionInterface;

class Pool
{
    protected \Swoole\Coroutine\Channel $pool;
    protected array $connections = [];
    protected bool $isClosed = false;
    protected Closure $connectionFactory;

    public function __construct(int $size, Closure $connectionFactory)
    {
        $this->pool = new \Swoole\Coroutine\Channel($size);
        $this->connectionFactory = $connectionFactory;

        $this->init();
    }

    public function __destruct()
    {
        if (!$this->isClosed) {
            $this->close();
        }
    }

    public function init(): void
    {
        for ($i = 0; $i < $this->pool->capacity; $i++) {
            $connection = ($this->connectionFactory)();

            $this->connections[] = $connection;
            $this->pool->push($connection);
        }
    }

    public function getSize(): int
    {
        if ($this->isClosed) {
            return -1;
        }

        return $this->pool->length();
    }

    public function push(PooledConnectionInterface $connection): void
    {
        $this->pool->push($connection);
    }

    /**
     * @return PooledConnectionInterface|\Illuminate\Database\Connection
     */
    public function get(): PooledConnectionInterface
    {
        return $this->pool->pop();
    }

    /**
     * Get connections without blocking
     * WARNING: Use it only when database interaction is not required
     *
     * @return PooledConnectionInterface[]|\Illuminate\Database\Connection[]
     */
    public function getRawConnections(): array
    {
        return $this->connections;
    }

    /**
     * Close connection pool, will free all connections in the pool
     */
    public function close(): void
    {
        $this->isClosed = true;

        while (!$this->pool->isEmpty()) {
            $connection = $this->get();
            $connection->disconnect();
        }

        $this->pool->close();
        $this->connections = [];
    }
}
