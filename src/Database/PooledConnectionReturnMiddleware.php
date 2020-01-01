<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Illuminate\Contracts\Container\Container;

/**
 * Useful for HTTP services, automatic return of taken connection
 */
class PooledConnectionReturnMiddleware
{
    private PooledDatabaseManager $dbManager;

    public function __construct(Container $container)
    {
        // db manager is resolved in constructor, because it's a singleton
        $this->dbManager = $container->get('db');
    }

    public function handle($request, \Closure $next)
    {
        return $next($request);
    }

    public function terminate(): void
    {
        $this->dbManager->freeTakenConnections();
    }
}
