<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;

class BehaviorTest extends TestCase
{
    public function testLogging(): void
    {
        $this->db->enableQueryLog();

        $connection = $this->db->connection();
        $connection->unprepared('SELECT 1');
        $connection->unprepared('SELECT 2');

        $log = $this->db->getQueryLog();
        $this->db->disableQueryLog();

        self::assertCount(2, $log);
    }

    public function testListen(): void
    {
        $log = [];

        $this->db->listen(function (QueryExecuted $event) use (&$log) {
            $log[] = ['sql' => $event->sql];
        });

        $connection = $this->db->connection();
        $connection->unprepared('SELECT 1');
        $connection->unprepared('SELECT 2');

        self::assertCount(2, $log);
    }

    public function testQueryException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "not_existing_table" does not exist');
    }

    public function testGetPdo(): void
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare('SELECT :binding AS t');
        $stmt->bindValue(':binding', '123');
        $stmt->execute();

        self::assertSame('123', $stmt->fetch()->t);
    }
}
