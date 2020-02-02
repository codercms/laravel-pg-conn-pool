<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Codercms\LaravelPgConnPool\Tests\Models\Author;
use Codercms\LaravelPgConnPool\Tests\Models\Book;

use function date;

class EloquentQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    public function testConnectionReturnedOnRelationEagerLoading(): void
    {
        $poolSize = 0;
        \Co\run(function () use (&$poolSize) {
            $author = Author::query()->forceCreate(['name' => 'Cobra']);
            Book::query()->forceCreate(['author_id' => $author->id, 'name' => 'Cobra']);

            Author::query()->with('books')->get();

            $poolSize = $this->getPoolSize();
            $this->disconnect();
        });

        $this->assertEquals(self::POOL_SIZE, $poolSize);
    }

    public function testConnectionReturnedOnRelationLazyLoading(): void
    {
        $poolSize = 0;
        \Co\run(function () use (&$poolSize) {
            $author = Author::query()->forceCreate(['name' => 'Cobra']);
            $books = $author->books;

            $this->assertTrue($author->relationLoaded('books'));

            $poolSize = $this->getPoolSize();
            $this->disconnect();
        });

        $this->assertEquals(self::POOL_SIZE, $poolSize);
    }

    private function createFakeAuthors(): void
    {
        $now = date('Y-m-d H:i:s');

        Author::query()->insert([
            ['name' => 'Cobra', 'created_at' => $now],
            ['name' => 'Black Mamba', 'created_at' => $now],
            ['name' => 'Python', 'created_at' => $now],
            ['name' => 'Taipan', 'created_at' => $now],
        ]);
    }

    public function testCursor(): void
    {
        $authors = [];

        \Co\run(function () use (&$authors) {
            $this->createFakeAuthors();

            $lazyCollection = Author::query()->orderBy('id')->cursor();

            // check connection pool size before cursor traversing
            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            foreach ($lazyCollection as $author) {
                // check connection pool size while cursor traversing
                $this->assertEquals(self::POOL_SIZE - 1, $this->getPoolSize());

                $authors[] = $author->name;
            }

            // check connection pool size after cursor traversing
            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            $this->disconnect();
        });

        $this->assertCount(4, $authors);
        $this->assertEquals(['Cobra', 'Black Mamba', 'Python', 'Taipan'], $authors);
    }

    public function testConnectionReturnedOnCursorError(): void
    {
        \Co\run(function () {
            $this->createFakeAuthors();

            $lazyCollection = Author::query()->orderBy('id')->cursor();

            // check connection pool size before cursor traversing
            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            try {
                foreach ($lazyCollection as $author) {
                    throw new \RuntimeException('bad');
                }
            } catch (\RuntimeException $e) {}

            // check connection pool size after cursor traversing
            $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());

            $this->disconnect();
        });
    }
}
