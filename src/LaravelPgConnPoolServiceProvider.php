<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool;

use Codercms\LaravelPgConnPool\Database\PoolManager;
use Codercms\LaravelPgConnPool\Database\PostgresConnection as PooledPostgresConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\ServiceProvider;

use function extension_loaded;

class LaravelPgConnPoolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (extension_loaded('swoole')) {
            $poolManager = new PoolManager();
            $this->app->singleton(PoolManager::class, fn() => $poolManager);

            $this->app->resolving(DatabaseManager::class, function (DatabaseManager $db) use ($poolManager) {
                $db->extend(
                    'pgsql_pool',
                    static function (array $config, string $name) use ($poolManager) {
                        $config['name'] = $name;

                        return new PooledPostgresConnection(
                            $poolManager->getPool($name, $config),
                            $config
                        );
                    }
                );
            });

            $this->app->terminating(static function () use ($poolManager) {
                $poolManager->close();
            });
        } else {
            // do not register package on systems without Swoole
            $this->app->bind(
                'db.connector.pgsql_pool',
                static function () {
                    return new PostgresConnector();
                }
            );

            Connection::resolverFor(
                'pgsql_pool',
                static function ($connection, $database, $prefix, $config) {
                    return new PostgresConnection($connection, $database, $prefix, $config);
                }
            );
        }
    }
}
