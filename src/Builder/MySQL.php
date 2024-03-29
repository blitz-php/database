<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder;

/**
 * Builder pour MySQL
 */
class MySQL extends BaseBuilder
{
    /**
     * Identifier escape character
     *
     * @var string
     */
    protected $escapeChar = '`';

    /**
     * Specifie quelles requetes requetes sql
     * supportent l'option IGNORE.
     */
    protected array $supportedIgnoreStatements = [
        'update' => 'IGNORE',
        'insert' => 'IGNORE',
        'delete' => 'IGNORE',
    ];

    /**
     * {@inheritDoc}
     */
    protected function _buildWhereDate(array $field, string $type, string $bool = 'and'): self
    {
        $bool = $bool === 'or' ? '|' : '';

        foreach ($field as $column => ['condition' => $condition, 'value' => $value]) {
            $field[$bool . $type . '(' . $this->db->escapeIdentifiers($column) . ') ' . $condition] = $value;
            unset($field[$column]);
        }

        return $this->where($field);
    }
}
