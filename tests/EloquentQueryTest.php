<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Tests;

use Codercms\LaravelPgConnPool\Tests\Models\Author;
use Codercms\LaravelPgConnPool\Tests\Models\Book;
use Swoole\Coroutine;

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

        self::assertSame(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConnectionReturnedOnRelationLazyLoading(): void
    {
        $author = Author::query()->forceCreate(['name' => 'Cobra']);
        $books = $author->books;

        self::assertTrue($author->relationLoaded('books'));

        self::assertSame(self::POOL_SIZE, $this->getPoolSize());
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
        self::assertSame(self::POOL_SIZE, $this->getPoolSize());

        foreach ($lazyCollection as $author) {
            // check connection pool size while cursor traversing
            self::assertSame(self::POOL_SIZE, $this->getPoolSize());

            $authors[] = $author->name;
        }

        // check connection pool size after cursor traversing
        self::assertSame(self::POOL_SIZE, $this->getPoolSize());

        self::assertCount(4, $authors);
        self::assertSame(['Cobra', 'Black Mamba', 'Python', 'Taipan'], $authors);
    }

    public function testConnectionReturnedOnCursorError(): void
    {
        $this->createFakeAuthors();

        $lazyCollection = Author::query()->orderBy('id')->cursor();

        // check connection pool size before cursor traversing
        self::assertSame(self::POOL_SIZE, $this->getPoolSize());

        try {
            foreach ($lazyCollection as $author) {
                throw new \RuntimeException('bad');
            }
        } catch (\RuntimeException $e) {}

        // check connection pool size after cursor traversing
        self::assertSame(self::POOL_SIZE, $this->getPoolSize());
    }

    public function testConcurrency(): void
    {
        $ch = new Coroutine\Channel(2);

        $func = static function (string $name) use ($ch) {
            try {
                $ch->push(Author::query()->forceCreate(['name' => $name]));
            } catch (\Throwable $e) {
                $ch->push($e);
            }
        };

        Coroutine::create($func, 'Cobra');
        Coroutine::create($func, 'Taipan');

        $names = [];

        for ($i = 0; $i < $ch->capacity; $i++) {
            $result = $ch->pop();

            self::assertInstanceOf(Author::class, $result);

            $names[] = $result->name;
        }

        self::assertContains('Cobra', $names);
        self::assertContains('Taipan', $names);
    }
}
