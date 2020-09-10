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
                            $poolManager->create($name, $config),
                            $config
                        );
                    }
                );
            });

            if (class_exists(\SwooleTW\Http\Coroutine\Context::class)) {
                // swooletw laravel-swoole package detected
                $this->app->terminating(static function () use ($poolManager) {
                    $request = \SwooleTW\Http\Coroutine\Context::getData('_request');

                    if ($request === null) {
                        $poolManager->close();
                    }
                });

                /** @var \Illuminate\Events\Dispatcher $eventDispatcher */
                $eventDispatcher = $this->app->make('events');
                $eventDispatcher->listen('swoole.workerStop', static function () use ($poolManager) {
                    $poolManager->close();
                });
            } else {
                $this->app->terminating(static function () use ($poolManager) {
                    $poolManager->close();
                });
            }
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
