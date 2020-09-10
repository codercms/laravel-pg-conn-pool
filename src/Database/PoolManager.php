<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use MakiseCo\Postgres\ConnectionConfig;
use MakiseCo\Postgres\ConnectionConfigBuilder;
use Swoole\Coroutine;

use function array_key_exists;
use function in_array;

class PoolManager
{
    /**
     * @var PostgresPool[]
     */
    private array $pools = [];

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        $pools = $this->pools;
        $this->pools = [];

        // close pools
        foreach ($pools as $pool) {
            Coroutine::create([$pool, 'close']);
        }
    }

    public function get(string $name): ?PostgresPool
    {
        return $this->pools[$name] ?? null;
    }

    public function create(string $name, array $config = []): PostgresPool
    {
        $pool = $this->get($name);
        if (null === $pool) {
            $connConfig = $this->getConnectionConfig($config);

            $this->pools[$name] = $pool = new PostgresPool($connConfig);

            $poolConfig = $this->getPoolConfig($config);
            $this->configurePool($pool, $poolConfig);

            $pool->init();
        }

        return $pool;
    }

    private function configurePool(PostgresPool $pool, array $config): void
    {
        if (array_key_exists('max_active', $config)) {
            $pool->setMaxActive($config['max_active']);
        }

        if (array_key_exists('min_active', $config)) {
            $pool->setMinActive($config['min_active']);
        }

        if (array_key_exists('validation_interval', $config)) {
            $pool->setValidationInterval($config['validation_interval']);
        }

        if (array_key_exists('max_wait_time', $config)) {
            $pool->setMaxWaitTime($config['max_wait_time']);
        }

        if (array_key_exists('max_idle_time', $config)) {
            $pool->setMaxIdleTime($config['max_idle_time']);
        }
    }

    private function getConnectionConfig(array $config): ConnectionConfig
    {
        foreach ($config as $key => $value) {
            if (!in_array($key, self::CONN_OPTIONS, true)) {
                unset($config[$key]);
            }
        }

        // do not use unbuffered mode in Laravel
        $config['unbuffered'] = false;

        return (new ConnectionConfigBuilder())
            ->fromArray($config)
            ->build();
    }

    private function getPoolConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            if (!in_array($key, self::POOL_OPTIONS, true)) {
                unset($config[$key]);
            }
        }

        return $config;
    }

    private const CONN_OPTIONS = [
        'host',
        'port',
        'user',
        'username',
        'password',
        'database',
        'dbname',
        'application_name',
        'timezone',
        'encoding',
        'charset',
        'search_path',
        'schema',
        'connect_timeout',
    ];

    private const POOL_OPTIONS = [
        'min_active',
        'max_active',
        'validation_interval',
        'max_wait_time',
        'max_idle_time',
    ];
}
