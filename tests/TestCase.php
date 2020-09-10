<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Codercms\LaravelPgConnPool\Database\PoolManager;
use Codercms\LaravelPgConnPool\LaravelPgConnPoolServiceProvider;
use Illuminate\Database\DatabaseManager;
use MakiseCo\Postgres\PostgresPool;

class TestCase extends CoroTestCase
{
    protected DatabaseManager $db;

    protected const POOL_SIZE = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->app->make('db');
    }

    protected function getPool(): PostgresPool
    {
        /** @var PoolManager $poolMgr */
        $poolMgr = $this->app->get(PoolManager::class);

        return $poolMgr->get('pgsql');
    }

    protected function getPoolSize(): int
    {
        return $this->getPool()->getIdleCount();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver'   => 'pgsql_pool',
            'max_active' => self::POOL_SIZE,
            'min_active' => self::POOL_SIZE,
            'host' => 'host.docker.internal',
            'database' => 'makise',
            'username' => 'makise',
            'password' => 'el-psy-congroo',
            'port' => 5432,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelPgConnPoolServiceProvider::class];
    }
}
