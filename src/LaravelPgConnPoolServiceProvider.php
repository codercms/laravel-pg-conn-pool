<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool;

use Codercms\LaravelPgConnPool\Database\PooledDatabaseManager;
use Codercms\LaravelPgConnPool\Database\Connection\PooledPostgresConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\ServiceProvider;

class LaravelPgConnPoolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // make alias of postgres connector for new driver
        $this->app->bind(
            'db.connector.pgsql_pool',
            function () {
                return new PostgresConnector();
            }
        );

        // do not register package on systems without swoole
        if (!\extension_loaded('swoole')) {
            // fallback from pooled connection to default postgres connection
            Connection::resolverFor(
                'pgsql_pool',
                function ($connection, $database, $prefix, $config) {
                    return new PostgresConnection($connection, $database, $prefix, $config);
                }
            );

            return;
        }

        // create resolving of connection instance for new driver
        Connection::resolverFor(
            'pgsql_pool',
            function ($connection, $database, $prefix, $config) {
                return new PooledPostgresConnection($connection, $database, $prefix, $config);
            }
        );

        // override (decorate) DatabaseManager
        $this->app->singleton(
            'db',
            function ($app) {
                return new PooledDatabaseManager($app, $app['db.factory']);
            }
        );
    }
}
