<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

class TransactionTest extends TestCase
{
    public function testConnectionReturnedAfterTransaction(): void
    {
        $this->db->transaction(function () {
            $connection = $this->db->connection();
            $connection->unprepared('SELECT 1');
            $connection->unprepared('SELECT 2');
        });

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionReturnedOnTransactionError(): void
    {
        try {
            $this->db->transaction(function () {
                throw new \RuntimeException('bad');
            });
        } catch (\RuntimeException $e) {}

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionNotReturnedAfterBeginTransaction(): void
    {
        $lazyConnection = $this->db->connection();
        $lazyConnection->beginTransaction();

        $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

        $lazyConnection->unprepared('SELECT 1');

        $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

        $lazyConnection->rollBack();
    }

    public function testConnectionReturnedAfterCommit(): void
    {
        $lazyConnection = $this->db->connection();
        $lazyConnection->beginTransaction();
        $lazyConnection->commit();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionReturnedAfterRollback(): void
    {
        $lazyConnection = $this->db->connection();
        $lazyConnection->beginTransaction();
        $lazyConnection->rollBack();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionNotReturnedOnNestedTransaction(): void
    {
        $lazyConnection = $this->db->connection();
        $lazyConnection->beginTransaction();
        $lazyConnection->beginTransaction();

        $lazyConnection->commit();

        $lazyConnection->unprepared('SELECT 1');

        $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

        $lazyConnection->rollBack();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }
}
