<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use MakiseCo\Connection\ConnectionInterface;
use MakiseCo\Postgres\Connection;
use MakiseCo\Postgres\PostgresPool as BasePool;

class PostgresPool extends BasePool
{
    public function pop(): Connection
    {
        return parent::pop();
    }

    public function push(ConnectionInterface $connection): int
    {
        return parent::push($connection);
    }
}
