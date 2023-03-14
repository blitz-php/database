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
 * Resultats Postgre
 */
class Postgre extends BaseResult
{
    /**
     * {@inheritDoc}
     */
    protected function _countField(): int
    {
        return pg_num_fields($this->query);
    }

    /**
     * {@inheritDoc}
     */
    public function fieldNames(): array
    {
        $fieldNames = [];

        for ($i = 0, $c = $this->countField(); $i < $c; $i++) {
            $fieldNames[] = pg_field_name($this->query, $i);
        }

        return $fieldNames;
    }

    /**
     * {@inheritDoc}
     */
    public function fieldData(): array
    {
        $retVal = [];

        for ($i = 0, $c = $this->countField(); $i < $c; $i++) {
            $retVal[$i]             = new stdClass();
            $retVal[$i]->name       = pg_field_name($this->query, $i);
            $retVal[$i]->type       = pg_field_type_oid($this->query, $i);
            $retVal[$i]->type_name  = pg_field_type($this->query, $i);
            $retVal[$i]->max_length = pg_field_size($this->query, $i);
            $retVal[$i]->length     = $retVal[$i]->max_length;
            // $retVal[$i]->primary_key = (int)($fieldData[$i]->flags & 2);
            // $retVal[$i]->default     = $fieldData[$i]->def;
        }

        return $retVal;
    }

    /**
     * {@inheritDoc}
     */
    public function _freeResult()
    {
        if ($this->query !== false) {
            pg_free_result($this->query);
            $this->query = false;
        }
    }

    /**
     * Moves the internal pointer to the desired offset. This is called
     * internally before fetching results to make sure the result set
     * starts at zero.
     *
     * @return mixed
     */
    public function dataSeek(int $n = 0)
    {
        return pg_result_seek($this->query, $n);
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
        $data = pg_fetch_all($this->query);
        pg_free_result($this->query);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    protected function _fetchAssoc()
    {
        return pg_fetch_assoc($this->query);
    }

    /**
     * {@inheritDoc}
     */
    protected function _fetchObject(string $className = 'stdClass')
    {
        return pg_fetch_object($this->query, null, $className);
    }
}
