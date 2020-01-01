<?php

declare(strict_types=1);

$asyncFn = function () {
    $dsn = 'pgsql:host=postgres;port=5432;dbname=postgres;user=postgres;password=secret';
    $pdo = new PDO($dsn);

    $pdo->exec('SELECT pg_sleep(2);');

    echo 'Coro ' . \Swoole\Coroutine::getCid() . PHP_EOL;
};

$start = microtime(true);

go($asyncFn);
go($asyncFn);

Swoole\Event::wait();

$end = \microtime(true);

echo 'Finish ' . ($end - $start);
