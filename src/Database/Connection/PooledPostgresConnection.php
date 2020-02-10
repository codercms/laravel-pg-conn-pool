<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database\Connection;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\PostgresConnection;

class PooledPostgresConnection extends PostgresConnection implements PooledConnectionInterface
{
    private static int $id = 0;
    private int $instanceId;

    /**
     * Cache of prepared statements to reuse them for performance reason
     *
     * @var \PDOStatement[]
     */
    private array $statements = [];

    private bool $cachePdoStatements = true;

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        static::$id++;
        $this->instanceId = static::$id;

        if (isset($config['cachePdoStatements'])) {
            $this->cachePdoStatements = (bool)$config['cachePdoStatements'];
        }
    }

    public function getId(): int
    {
        return $this->instanceId;
    }

    public function disconnect(): void
    {
        parent::disconnect();

        $this->statements = [];
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        if (!$this->cachePdoStatements) {
            return parent::select($query, $bindings, $useReadPdo);
        }

        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $queryHash = \md5($query);
            $statement = $this->statements[$queryHash] ?? null;

            if (null === $statement) {
                // For select statements, we'll simply execute the query and return an array
                // of the database result set. Each element in the array will be a single
                // row from the database table, and will either be an array or objects.
                $this->statements[$queryHash] = $statement = $this->prepared(
                    $this
                        ->getPdoForSelect($useReadPdo)
                        ->prepare($query)
                );
            }

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement->fetchAll();
        });
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null): void
    {
        $this->event(new QueryExecuted($query, $bindings, $time, $this));

        if ($this->loggingQueries) {
            $id = $this->getId();

            $this->queryLog[] = compact('id', 'query', 'bindings', 'time');
        }
    }
}
