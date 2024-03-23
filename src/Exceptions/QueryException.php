<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Exceptions;

use BlitzPHP\Utilities\String\Text;
use PDOException;
use Throwable;

class QueryException extends PDOException
{
    /**
     * Create a new query exception instance.
     *
     * @param  string  $connectionName The database connection name.
     * @param  string  $sql The SQL for the query.
     * @param  array  $bindings The bindings for the query.
     */
    public function __construct(public string $connectionName, protected string $sql, protected array $bindings, Throwable $previous)
    {
        parent::__construct('', 0, $previous);

        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($connectionName, $sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Format the SQL error message.
     */
    protected function formatMessage(string $connectionName, string $sql, array $bindings, Throwable $previous):  string
    {
        return $previous->getMessage().' (Connection: '.$connectionName.', SQL: '. Text::replaceArray('?', $bindings, $sql).')';
    }

    /**
     * Get the connection name for the query.
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Get the SQL for the query.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the bindings for the query.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
