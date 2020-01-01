<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Illuminate\Database\PostgresConnection;

class PooledPostgresConnection extends PostgresConnection implements PooledConnectionInterface
{
    private static int $id = 0;
    private int $instanceId;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        static::$id++;
        $this->instanceId = static::$id;
    }

    public function getId(): int
    {
        return $this->instanceId;
    }
}
