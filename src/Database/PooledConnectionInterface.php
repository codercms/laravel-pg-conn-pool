<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

interface PooledConnectionInterface
{
    public function getId(): int;
}
