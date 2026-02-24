<?php
namespace BlitzPHP\Database\Spec\Mock;

use BlitzPHP\Contracts\Database\ResultInterface;
use BlitzPHP\Database\Connection\SQLite;

class MockConnection extends SQLite
{
    /**
     * {@inheritDoc}
     */
    public string $escapeChar = '';

    protected array $returnValues = [];

    /**
     * {@inheritDoc}
     */
    public $lastQuery;

    public function shouldReturn(string $method, $return): self
    {
        $this->returnValues[$method] = $return;

        return $this;
    }

    protected function afterConnect(): void
    {
        
    }

    /**
     * {@inheritDoc}
     */
    protected function getDsn(): string
    {
        return 'sqlite::memory:';
    }

    /**
     * {@inheritDoc}
     */
    public function setDatabase(string $databaseName): bool
    {
        $this->config['database'] = $databaseName;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getDriver(): string
    {
        $this->initialize();

        return 'mysql';
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Executes the query against the database.
     *
     * @return mixed
     */
    protected function execute(string $sql, array $params = [])
    {
        return $this->returnValues['execute'];
    }

    /**
     * Returns the total number of rows affected by this query.
     */
    public function affectedRows(): int
    {
        return 1;
    }
    
    /**
     * Returns the total number of rows affected by this query.
     */
    public function numRows(): int
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     */
    public function error(): array
    {
        return [
            'code'    => 0,
            'message' => '',
        ];
    }

    /**
     * Insert ID
     *
     * @return int|string
     */
    public function insertID(?string $table = null)
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     */
    protected function _escapeString(string $str): string
    {
        return "'" . parent::_escapeString($str) . "'";
    }
}
