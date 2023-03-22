<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Result;

use BlitzPHP\Database\Exceptions\DatabaseException;
use Closure;
use SQLite3Result;
use stdClass;

/**
 * Result pour SQLite
 */
class SQLite extends BaseResult
{
    /**
     * @var SQLite3Result
     */
    protected $query;

    /**
     * {@inheritDoc}
     */
    protected function _countField(): int
    {
        return $this->query->numColumns();
    }

    /**
     * {@inheritDoc}
     */
    protected function _result($type): array
    {
        return $this->_resultArray();
    }

    /**
     * {@inheritDoc}
     */
    protected function _resultArray(): array
    {
        $data = [];

        while ($row = $this->query->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        $this->query->finalize();

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function _resultObject(): array
    {
        return array_map(static fn ($data) => (object) $data, $this->_resultArray());
    }

    /**
     * {@inheritDoc}
     */
    public function fieldNames(): array
    {
        $fieldNames = [];

        for ($i = 0, $c = $this->countField(); $i < $c; $i++) {
            $fieldNames[] = $this->query->columnName($i);
        }

        return $fieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function fieldData(): array
    {
        static $dataTypes = [
            SQLITE3_INTEGER => 'integer',
            SQLITE3_FLOAT   => 'float',
            SQLITE3_TEXT    => 'text',
            SQLITE3_BLOB    => 'blob',
            SQLITE3_NULL    => 'null',
        ];

        $retVal = [];
        $this->query->fetchArray(SQLITE3_NUM);

        for ($i = 0, $c = $this->countField(); $i < $c; $i++) {
            $retVal[$i]             = new stdClass();
            $retVal[$i]->name       = $this->query->columnName($i);
            $type                   = $this->query->columnType($i);
            $retVal[$i]->type       = $type;
            $retVal[$i]->type_name  = $dataTypes[$type] ?? null;
            $retVal[$i]->max_length = null;
            $retVal[$i]->length     = null;
        }
        $this->query->reset();

        return $retVal;
    }

    /**
     * {@inheritDoc}
     */
    protected function _freeResult()
    {
        if (is_object($this->query)) {
            $this->query->finalize();
            $this->query = false;
        }
    }

    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @throws DatabaseException
     *
     * @return mixed
     */
    public function dataSeek(int $n = 0)
    {
        if ($n !== 0) {
            throw new DatabaseException('SQLite3 doesn\'t support seeking to other offset.');
        }

        return $this->query->reset();
    }

    /**
     * {@inheritDoc}
     */
    protected function _fetchAssoc()
    {
        return $this->query->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * {@inheritDoc}
     *
     * @return bool|Entity|object
     */
    protected function _fetchObject(string $className = 'stdClass')
    {
        // No native support for fetching rows as objects
        if (($row = $this->fetchAssoc()) === false) {
            return false;
        }

        if ($className === 'stdClass') {
            return (object) $row;
        }

        $classObj = new $className();

        if (is_subclass_of($className, Entity::class)) {
            return $classObj->setAttributes($row);
        }

        $classSet = Closure::bind(function ($key, $value) {
            $this->{$key} = $value;
        }, $classObj, $className);

        foreach (array_keys($row) as $key) {
            $classSet($key, $row[$key]);
        }

        return $classObj;
    }
}
