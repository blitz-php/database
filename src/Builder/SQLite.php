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
 * Builder pour SQLite
 */
class SQLite extends BaseBuilder
{
    /**
     * Les installations par défaut de SQLite n'autorisent pas
     * la limitation des clauses de suppression.
     */
    protected bool $canLimitDeletes = false;

    /**
     * Les installations par défaut de SQLite n'autorisent pas
     * les requêtes de mise à jour limitées avec WHERE.
     */
    protected bool $canLimitWhereUpdates = false;

    /**
     * Mots cles pour ORDER BY random
     */
    protected array $randomKeyword = [
        'RANDOM()',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $supportedIgnoreStatements = [
        'insert' => 'OR IGNORE',
    ];

    /**
     * {@inheritDoc}
     */
    protected function _replaceStatement(string $table, string $keys, string $values)
    {
        return [
            'INSERT OR ',
            ...parent::_replaceStatement($table, $keys, $values),
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function _truncateStatement(string $table): string
    {
        return 'DELETE FROM ' . $table;
    }
}
