<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

class Pool
{
    protected \Swoole\Coroutine\Channel $pool;
    private int $size;

    public function __construct(int $size)
    {
        $this->pool = new \Swoole\Coroutine\Channel($size);
        $this->size = $size;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function push(PooledConnectionInterface $connection): void
    {
        $this->pool->push($connection);
    }

    public function get(): PooledConnectionInterface
    {
        return $this->pool->pop();
    }

    public function close(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->get();
            $connection->disconnect();
        }

        $this->pool->close();
    }
}
