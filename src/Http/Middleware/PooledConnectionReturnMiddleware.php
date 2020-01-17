<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Http\Middleware;

use Codercms\LaravelPgConnPool\Database\PooledDatabaseManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;

/**
 * Useful for HTTP services, automatic return of taken connection
 */
class PooledConnectionReturnMiddleware
{
    private DatabaseManager $dbManager;

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
        // prevent crashing on systems without swoole
        if ($this->dbManager instanceof PooledDatabaseManager) {
            $this->dbManager->freeTakenConnections();
        }
    }
}
