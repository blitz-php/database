<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Connection;

use stdClass;

/**
 * Connexion PostgreSQL
 */
class Postgre extends BaseConnection
{
    /**
     * Caractère d'échappement PostgreSQL
     */
    protected string $escapeChar = '"';

    /**
     * {@inheritDoc}
     */
    protected string $disableForeignKeyChecks = 'SET CONSTRAINTS ALL DEFERRED';

    /**
     * {@inheritDoc}
     */
    protected string $enableForeignKeyChecks = 'SET CONSTRAINTS ALL IMMEDIATE';

    /**
     * {@inheritDoc}
     */
    protected function getDsn(): string
    {
        if (! empty($this->config['dsn'])) {
            return $this->config['dsn'];
        }

        $dsn = "pgsql:host={$this->config['hostname']}";

        if (! empty($this->config['port'])) {
            $dsn .= ";port={$this->config['port']}";
        }

        if (! empty($this->config['database'])) {
            $dsn .= ";dbname={$this->config['database']}";
        }

        return $dsn;
    }

    /**
     * {@inheritDoc}
     */
    protected function afterConnect(): void
    {
        // Configuration du schéma
        $schema = $this->config['schema'] ?? 'public';
        $this->pdo->exec("SET search_path TO {$schema}");

        // Configuration du charset
        if (! empty($this->config['charset'])) {
            $this->pdo->exec("SET NAMES '{$this->config['charset']}'");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function _listTables(bool $constrainByPrefix = false): string
    {
        $sql = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('information_schema','pg_catalog')";

        if ($constrainByPrefix && $this->getPrefix() !== '') {
            $sql .= " AND tablename LIKE '" . $this->getPrefix() . "%'";
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function _listIndexes(string $table): array
    {
        $sql = '
            SELECT "indexname", "indexdef"
			FROM "pg_indexes"
			WHERE LOWER("tablename") = ' . $this->escape(strtolower($this->prefix . $table)) . '
			AND "schemaname" = ' . $this->escape('public');

        $rows    = $this->query($sql)->resultObject();
        $indexes = [];

        foreach ($rows as $row) {
            $index          = new stdClass();
            $index->name    = $row->indexname;
            $_columns       = explode(',', preg_replace('/^.*\((.+?)\)$/', '$1', trim($row->indexdef)));
            $index->columns = array_map(static fn ($v) => trim($v), $_columns);

            if (str_starts_with($row->indexdef, 'CREATE UNIQUE INDEX pk')) {
                $index->type = 'PRIMARY';
            } else {
                $index->type = (str_starts_with($row->indexdef, 'CREATE UNIQUE')) ? 'UNIQUE' : 'INDEX';
            }

            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * {@inheritDoc}
     */
    public function _listColumns(string $table): array
    {
        $sql = 'SELECT "column_name", "data_type", "character_maximum_length", "numeric_precision", "column_default",  "is_nullable"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = '
                . $this->escape(strtolower($this->prefix . $table))
                . ' ORDER BY "ordinal_position"';

        $rows    = $this->query($sql)->resultObject();
        $columns = [];

        foreach ($rows as $row) {
            $column             = new stdClass();
            $column->name       = $row->column_name;
            $column->type       = $row->data_type;
            $column->nullable   = $row->is_nullable === 'YES';
            $column->default    = $row->column_default;
            $column->max_length = $row->character_maximum_length > 0 ? $row->character_maximum_length : $row->numeric_precision;

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function _listForeignKeys(string $table): array
    {
        $sql = 'SELECT c.constraint_name,
            x.table_name,
            x.column_name,
            y.table_name as foreign_table_name,
            y.column_name as foreign_column_name,
            c.delete_rule,
            c.update_rule,
            c.match_option
            FROM information_schema.referential_constraints c
            JOIN information_schema.key_column_usage x
                on x.constraint_name = c.constraint_name
            JOIN information_schema.key_column_usage y
                on y.ordinal_position = x.position_in_unique_constraint
                and y.constraint_name = c.unique_constraint_name
            WHERE x.table_name = ' . $this->escape($this->prefix . $table) .
            'order by c.constraint_name, x.ordinal_position';

        $rows = $this->query($sql)->resultObject();
        $keys = [];

        foreach ($rows as $row) {
            $key                      = new stdClass();
            $key->constraint_name     = $row->constraint_name;
            $key->table_name          = $table;
            $key->column_name         = $row->column_name;
            $key->foreign_table_name  = $row->foreign_table_name;
            $key->foreign_column_name = $row->foreign_column_name;
            $key->on_delete           = $row->delete_rule;
            $key->on_update           = $row->update_rule;
            $key->match               = $row->match_option;

            $keys[] = $key;
        }

        return $keys;
    }
}
