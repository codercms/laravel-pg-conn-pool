<?php

declare(strict_types=1);

namespace Codercms\LaravelPgConnPool\Database;

use Closure;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use Throwable;

use function is_int;
use function is_string;
use function substr;

class PDOStatement extends \PDOStatement
{
    use SqlErrorHelper;

    private ?Statement $statement;
    private array $params = [];
    private int $fetchMode = ResultSet::FETCH_OBJECT;

    /**
     * @var CommandResult|ResultSet
     */
    private $result;

    private ?Closure $release;

    public function __construct(Statement $statement, Closure $release)
    {
        $this->statement = $statement;
        $this->release = $release;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function close(): void
    {
        if ($this->statement !== null) {
            $release = $this->release;

            $this->release = null;
            $this->statement = null;

            $release();
        }
    }

    public function bindValue($parameter, $value, $data_type = 2): void
    {
        if (is_int($parameter)) {
            $parameter--;
        } elseif (is_string($parameter) && $parameter[0] === ':') {
            $parameter = substr($parameter, 1);
        }

        $this->params[$parameter] = $value;
    }

    public function execute($args = null): bool
    {
        if ($this->statement === null) {
            throw new \RuntimeException('Statement is closed');
        }

        try {
            $this->result = $this->statement->execute($this->params);
            $this->params = [];
        } catch (Throwable $e) {
            throw self::handleQueryError($e);
        } finally {
            // free connection as early as possible
            $this->close();
        }

        return true;
    }

    public function fetch($fetch_style = null, $cursor_orientation = 0, $cursor_offset = 0)
    {
        if (!$this->result instanceof ResultSet) {
            return null;
        }

        return $this->result->fetch($this->fetchMode);
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null): array
    {
        if (!$this->result instanceof ResultSet) {
            return [];
        }

        return $this->result->fetchAll($this->fetchMode);
    }

    public function rowCount(): int
    {
        if (!$this->result instanceof CommandResult) {
            return 0;
        }

        return $this->result->getAffectedRowCount();
    }

    public function columnCount(): int
    {
        if (!$this->result instanceof ResultSet) {
            return 0;
        }

        return $this->result->getFieldCount();
    }

    public function setFetchMode($mode, $classNameObject = null, $ctor_args = null): void
    {
        // fallback to fetch object
        $driverMode = ResultSet::FETCH_OBJECT;

        // \PDO::FETCH_ASSOC
        if ($mode === 2) {
            $driverMode = ResultSet::FETCH_ASSOC;
        }

        $this->fetchMode = $driverMode;
    }
}
