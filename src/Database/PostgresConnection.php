<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Swoole\Coroutine;

class PostgresConnection extends \Illuminate\Database\PostgresConnection
{
    private const CONTEXT_KEY = 'makise_pgsql_pdo_wrapper';
    private PostgresPool $pool;

    /** @noinspection MagicMethodsValidityInspection */
    public function __construct(PostgresPool $pool, array $config)
    {
        $this->pool = $pool;

        // type commands are run such as checking whether a table exists.
        $this->database = $config['database'] ?? $config['dbname'] ?? '';

        $this->tablePrefix = $config['table_prefix'] ?? $config['prefix'] ?? '';

        $this->config = $config;

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultSchemaGrammar();

        $this->useDefaultPostProcessor();
    }

    public function getPdo(): PDOWrapper
    {
        $wrapper = $this->getCoroPdo();
        if (null === $wrapper) {
            $this->setCoroPdo($wrapper = new PDOWrapper($this->pool));
        }

        return $wrapper;
    }

    public function disconnect(): void
    {
        unset(Coroutine::getContext()['makise_pgsql_pdo_wrapper']);
    }

    public function reconnect(): void
    {
        $this->setCoroPdo(new PDOWrapper($this->pool));
    }

    protected function reconnectIfMissingConnection(): void
    {
        $wrapper = $this->getCoroPdo();
        if (null === $wrapper || !$wrapper->isAlive()) {
            $this->reconnect();
        }
    }

    protected function createSavepoint(): void
    {
        $this->getPdo()->createSavepoint('trans'.($this->transactions + 1));
    }

    protected function performRollBack($toLevel): void
    {
        if ($toLevel === 0) {
            $this->getPdo()->rollBack();
        } elseif ($this->queryGrammar->supportsSavepoints()) {
            $this->getPdo()->rollbackTo($toLevel, 'trans'.($toLevel + 1));
        }
    }

    private function getCoroPdo(): ?PDOWrapper
    {
        $wrapper = Coroutine::getContext()[self::CONTEXT_KEY] ?? null;
        if ($wrapper instanceof PDOWrapper) {
            return $wrapper;
        }

        return null;
    }

    private function setCoroPdo(PDOWrapper $wrapper): void
    {
        Coroutine::getContext()[self::CONTEXT_KEY] = $wrapper;
    }
}
