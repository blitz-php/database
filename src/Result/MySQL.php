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

use stdClass;

/**
 * Result pour MySQL
 */
class MySQL extends BaseResult
{
    /**
     * {@inheritDoc}
     */
    protected function _countField(): int
    {
        return $this->query->field_count;
    }

    /**
     * {@inheritDoc}
     */
    protected function _result($type): array
    {
        $data = [];

        while ($row = $this->query->fetch_object($type)) {
            $data[] = $row;
        }

        $this->query->close();

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function _resultArray(): array
    {
        $data = [];

        while ($row = $this->query->fetch_assoc()) {
            $data[] = $row;
        }

        $this->query->close();

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function _resultObject(): array
    {
        $data = [];

        while ($row = $this->query->fetch_object()) {
            $data[] = $row;
        }

        $this->query->close();

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function fieldNames(): array
    {
        $fieldNames = [];
        $this->query->field_seek(0);

        while ($field = $this->query->fetch_field()) {
            $fieldNames[] = $field->name;
        }

        return $fieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function fieldData(): array
    {
        static $dataTypes = [
            MYSQLI_TYPE_DECIMAL    => 'decimal',
            MYSQLI_TYPE_NEWDECIMAL => 'newdecimal',
            MYSQLI_TYPE_FLOAT      => 'float',
            MYSQLI_TYPE_DOUBLE     => 'double',

            MYSQLI_TYPE_BIT      => 'bit',
            MYSQLI_TYPE_SHORT    => 'short',
            MYSQLI_TYPE_LONG     => 'long',
            MYSQLI_TYPE_LONGLONG => 'longlong',
            MYSQLI_TYPE_INT24    => 'int24',

            MYSQLI_TYPE_YEAR => 'year',

            MYSQLI_TYPE_TIMESTAMP => 'timestamp',
            MYSQLI_TYPE_DATE      => 'date',
            MYSQLI_TYPE_TIME      => 'time',
            MYSQLI_TYPE_DATETIME  => 'datetime',
            MYSQLI_TYPE_NEWDATE   => 'newdate',

            MYSQLI_TYPE_SET => 'set',

            MYSQLI_TYPE_VAR_STRING => 'var_string',
            MYSQLI_TYPE_STRING     => 'string',

            MYSQLI_TYPE_GEOMETRY    => 'geometry',
            MYSQLI_TYPE_TINY_BLOB   => 'tiny_blob',
            MYSQLI_TYPE_MEDIUM_BLOB => 'medium_blob',
            MYSQLI_TYPE_LONG_BLOB   => 'long_blob',
            MYSQLI_TYPE_BLOB        => 'blob',
        ];

        $retVal    = [];
        $fieldData = $this->query->fetch_fields();

        foreach ($fieldData as $i => $data) {
            $retVal[$i]              = new stdClass();
            $retVal[$i]->name        = $data->name;
            $retVal[$i]->type        = $data->type;
            $retVal[$i]->type_name   = in_array($data->type, [1, 247], true) ? 'char' : ($dataTypes[$data->type] ?? null);
            $retVal[$i]->max_length  = $data->max_length;
            $retVal[$i]->primary_key = $data->flags & 2;
            $retVal[$i]->length      = $data->length;
            $retVal[$i]->default     = $data->def;
        }

        return $retVal;
    }

    /**
     * {@inheritDoc}
     */
    protected function _freeResult()
    {
        if (is_object($this->query)) {
            $this->query->free();
            $this->query = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function _fetchAssoc()
    {
        return $this->query->fetch_assoc();
    }

    /**
     * {@inheritDoc}
     *
     * @return bool|Entity|object
     */
    protected function _fetchObject(string $className = 'stdClass')
    {
        return $this->query->fetch_object($className);
    }
}
