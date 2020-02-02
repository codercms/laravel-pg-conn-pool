<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

class TransactionTest extends TestCase
{
    public function testConnectionReturnedAfterTransaction(): void
    {
        $poolSize = 0;
        \Co\run(function () use (&$poolSize) {
            $this->db->transaction(function () {
                $connection = $this->db->connection();
                $connection->unprepared('SELECT 1');
                $connection->unprepared('SELECT 2');
            });

            $poolSize = $this->getPoolSize();
            $this->disconnect();
        });

        $this->assertEquals(self::POOL_SIZE, $poolSize);
    }

    public function testConnectionReturnedOnTransactionError(): void
    {
        $poolSize = 0;
        \Co\run(function () use (&$poolSize) {
            try {
                $this->db->transaction(function () {
                    throw new \RuntimeException('bad');
                });
            } catch (\RuntimeException $e) {}

            $poolSize = $this->getPoolSize();
            $this->disconnect();
        });

        $this->assertEquals(self::POOL_SIZE, $poolSize);
    }

    public function testConnectionNotReturnedAfterBeginTransaction(): void
    {
        \Co\run(function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->beginTransaction();

            $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

            $lazyConnection->unprepared('SELECT 1');

            $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

            $lazyConnection->rollBack();
            $this->disconnect();
        });
    }

    public function testConnectionReturnedAfterCommit(): void
    {
        \Co\run(function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->beginTransaction();
            $lazyConnection->commit();

            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            $this->disconnect();
        });
    }

    public function testConnectionReturnedAfterRollback(): void
    {
        \Co\run(function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->beginTransaction();
            $lazyConnection->rollBack();

            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            $this->disconnect();
        });
    }

    public function testConnectionNotReturnedOnNestedTransaction(): void
    {
        \Co\run(function () {
            $lazyConnection = $this->db->connection();
            $lazyConnection->beginTransaction();
            $lazyConnection->beginTransaction();

            $lazyConnection->commit();

            $lazyConnection->unprepared('SELECT 1');

            $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

            $lazyConnection->rollBack();

            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            $this->disconnect();
        });
    }
}
