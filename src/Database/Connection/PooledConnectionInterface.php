<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database\Connection;

interface PooledConnectionInterface
{
    public function getId(): int;
}
