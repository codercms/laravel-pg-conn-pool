<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Codercms\LaravelPgConnPool\LaravelPgConnPoolServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * @var \Codercms\LaravelPgConnPool\Database\PooledDatabaseManager
     */
    protected $db;

    protected const POOL_SIZE = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->app->make('db');
    }

    /**
     * Run test cases in the coroutine
     *
     * @return void|null
     * @throws \Throwable
     */
    protected function runTest()
    {
        $result = null;
        /* @var \Throwable|null $ex */
        $ex = null;

        \Co\run(function () use (&$result, &$ex) {
            try {
                $result = parent::runTest();
            } catch (\Throwable $e) {
                $ex = $e;
            }

            $this->disconnect();
        });

        if (null !== $ex) {
            throw $ex;
        }

        return $result;
    }

    protected function disconnect(): void
    {
        $this->db->disconnect('pgsql');
    }

    protected function getPoolSize(): int
    {
        return $this->db->getPool('pgsql')->getSize();
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
            'poolSize' => self::POOL_SIZE,
            'host' => '127.0.0.1',
            'database' => 'forge',
            'username' => 'forge',
            'password' => 'secret',
            'port' => 5433,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelPgConnPoolServiceProvider::class];
    }
}
