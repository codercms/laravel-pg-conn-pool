<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Illuminate\Database\Events\QueryExecuted;

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

        $this->assertCount(2, $log);
        $this->assertArrayHasKey('id', $log[0]);

        $nextId = $log[0]['id'] + 1;

        $this->assertEquals($nextId, $log[1]['id']);
    }

    public function testListen(): void
    {
        $log = [];

        $this->db->listen(function (QueryExecuted $event) use (&$log) {
            $log[] = ['id' => $event->connection->getId(), 'sql' => $event->sql];
        });

        $connection = $this->db->connection();
        $connection->unprepared('SELECT 1');
        $connection->unprepared('SELECT 2');

        $this->assertCount(2, $log);
        $this->assertArrayHasKey('id', $log[0]);

        $nextId = $log[0]['id'] + 1;

        $this->assertEquals($nextId, $log[1]['id']);
    }

    public function testConnectionIsNotTakenFromThePool(): void
    {
        $this->db->connection();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testGetConnection(): void
    {
        $lazyConnection = $this->db->connection();
        $connection = $lazyConnection->getConnection();

        $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

        $lazyConnection->returnConnection();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testGetPdo(): void
    {
        $connection = $this->db->connection();
        $pdo = $connection->getPdo();

        $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

        $connection->returnConnection();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testFreeTakenConnections(): void
    {
        (function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->getConnection();

            $this->db->freeTakenConnections();
        })();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testForgetLazyConnection(): void
    {
        (function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->getConnection();

            $this->db->forgetLazyConnection($lazyConnection);
        })();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testReconnectOnDisconnected(): void
    {
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
    }
}
