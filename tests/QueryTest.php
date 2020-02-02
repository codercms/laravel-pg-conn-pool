<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Illuminate\Database\Events\QueryExecuted;

class QueryTest extends TestCase
{
    public function testLogging(): void
    {
        $log = [];
        \Co\run(function () use (&$log) {
            $this->db->enableQueryLog();

            $connection = $this->db->connection();
            $connection->unprepared('SELECT 1');
            $connection->unprepared('SELECT 2');

            $log = $this->db->getQueryLog();
            $this->db->disableQueryLog();

            $this->disconnect();
        });

        $this->assertCount(2, $log);
        $this->assertArrayHasKey('id', $log[0]);

        $nextId = $log[0]['id'] + 1;

        $this->assertEquals($nextId, $log[1]['id']);
    }

    public function testListen(): void
    {
        $log = [];

        \Co\run(function () use (&$log) {
            $this->db->listen(function (QueryExecuted $event) use (&$log) {
                $log[] = ['id' => $event->connection->getId(), 'sql' => $event->sql];
            });

            $connection = $this->db->connection();
            $connection->unprepared('SELECT 1');
            $connection->unprepared('SELECT 2');

            $this->disconnect();
        });

        $this->assertCount(2, $log);
        $this->assertArrayHasKey('id', $log[0]);

        $nextId = $log[0]['id'] + 1;

        $this->assertEquals($nextId, $log[1]['id']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testReconnectOnDisconnected(): void
    {
        \Co\run(function () {
            $pool = $this->db->getPool('pgsql');
            for ($i = 0; $i < self::POOL_SIZE; $i++) {
                $connection = $pool->get();
                $connection->disconnect();
                $pool->push($connection);
            }

            $lazyConnection = $this->db->connection();

            // test everything is fine
            for ($i = 0; $i < self::POOL_SIZE; $i++) {
                $lazyConnection->unprepared('SELECT 1');
            }

            $this->disconnect();
        });
    }
}
