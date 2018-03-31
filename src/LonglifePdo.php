<?php

namespace Anonymous\Longlife;


/**
 * Class LonglifePdo
 * @author Anonymous PHP Developer
 * @package Anonymous\Longlife
 */
class LonglifePdo extends \Aura\Sql\ExtendedPdo
{

    protected $checkConnectionTimeout;
    protected $lastAccessTime;

    protected $statementsCache = [];
    protected $statementsCacheLimit;

    protected $hasSavepoint;
    protected $savepointDrivers = ['mysql', 'pgsql'];
    protected $savepointDepth = 0;

    /**
     * @inheritdoc
     */
    public function connect()
    {
        $connect = false;

        if (!$this->pdo instanceof \PDO) {
            $connect = true;
        } elseif ($this->checkConnectionTimeout && time() - $this->lastAccessTime >= $this->checkConnectionTimeout) {
            // PDO can bring warning instead of Exception
            set_error_handler(function () { throw new \Exception(); });

            try {
                $connect = !(bool)$this->pdo->query('SELECT 1 + 1');
            } catch (\Exception $e) {
                $connect = true;
            }

            restore_error_handler();
        }

        if ($connect) {
            $this->pdo = null;
            $this->statementsCache = [];
            parent::connect();

            $this->hasSavepoint = in_array($this->getAttribute(\PDO::ATTR_DRIVER_NAME), $this->savepointDrivers);
        }

        $this->lastAccessTime = time();
    }

    /**
     * @inheritdoc
     */
    public function prepare($statement, $options = null)
    {
        $this->connect();

        return $this->getPreparedStatement($statement, $options);
    }

    /**
     * Sets check connection timeout. No checks by default
     *
     * @param integer $timeout Seconds
     */
    public function setCheckConnectionTimeout($timeout)
    {
        $this->checkConnectionTimeout = $timeout;
    }

    /**
     * Sets statements cache limit. No cache by default
     *
     * @param integer $limit Size of cache
     */
    public function setStatementsCacheLimit($limit)
    {
        $this->statementsCacheLimit = $limit;
    }

    /**
     * Returns cached statement or prepares new one
     *
     * @param string $statement
     * @param array $options
     * @return \PDOStatement
     */
    protected function getPreparedStatement($statement, $options = null)
    {
        // No limit no cache
        if (!$this->statementsCacheLimit) {
            return $this->pdo->prepare($statement);
        }

        $hash = md5($statement . (!empty($options) ? serialize($options) : ''));

        if (isset($this->statementsCache[$hash])) {
            $prepared = $this->statementsCache[$hash];
            unset($this->statementsCache[$hash]);
        } else {
            $prepared = $this->pdo->prepare($statement, $options);
        }

        // Add last requested statement to the end of the list
        $this->statementsCache[$hash] = $prepared;

        // Slice cache if limit achieved
        if (count($this->statementsCache) > $this->statementsCacheLimit) {
            $this->statementsCache = array_slice($this->statementsCache, -$this->statementsCacheLimit, null, true);
        }

        return $prepared;
    }

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        if ($this->savepointDepth == 0 || !$this->hasSavepoint) {
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT LEVEL{$this->savepointDepth}");
        }

        ++$this->savepointDepth;
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        --$this->savepointDepth;

        if ($this->savepointDepth == 0 || !$this->hasSavepoint) {
            parent::commit();
        } else {
            $this->exec("RELEASE SAVEPOINT LEVEL{$this->savepointDepth}");
        }
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        if ($this->savepointDepth == 0) {
            throw new \PDOException('Rollback error: There is no transaction started');
        }

        --$this->savepointDepth;

        if ($this->savepointDepth == 0 || !$this->hasSavepoint) {
            parent::rollBack();
        } else {
            $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->savepointDepth}");
        }
    }

}