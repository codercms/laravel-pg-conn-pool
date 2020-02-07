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
        $author = Author::query()->forceCreate(['name' => 'Cobra']);
        Book::query()->forceCreate(['author_id' => $author->id, 'name' => 'Cobra']);

        Author::query()->with('books')->get();

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionReturnedOnRelationLazyLoading(): void
    {
        $author = Author::query()->forceCreate(['name' => 'Cobra']);
        $books = $author->books;

        $this->assertTrue($author->relationLoaded('books'));

        $this->assertEquals(self::POOL_SIZE, $this->getPoolSize());
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

        $this->assertCount(4, $authors);
        $this->assertEquals(['Cobra', 'Black Mamba', 'Python', 'Taipan'], $authors);
    }

    public function testConnectionReturnedOnCursorError(): void
    {
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
    }
}
