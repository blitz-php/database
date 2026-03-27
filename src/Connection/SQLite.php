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

use BlitzPHP\Database\Exceptions\DatabaseException;
use stdClass;

/**
 * Connexion SQLite
 */
class SQLite extends BaseConnection
{
    /**
     * Caractère d'échappement SQLite
     */
    protected string $escapeChar = '"';

    /**
     * {@inheritDoc}
     */
    protected string $disableForeignKeyChecks = 'PRAGMA foreign_keys = OFF';

    /**
     * {@inheritDoc}
     */
    protected string $enableForeignKeyChecks = 'PRAGMA foreign_keys = ON';

    /**
     * {@inheritDoc}
     */
    protected function getDsn(): string
    {
        if (! empty($this->config['dsn'])) {
            return $this->config['dsn'];
        }

        $database = $this->config['database'];

        if ($database === ':memory:') {
            return 'sqlite::memory:';
        }

        if (! file_exists($database) && ! is_writable(dirname($database))) {
            throw new DatabaseException(
                "Impossible de créer la base de données SQLite : le répertoire n'est pas accessible en écriture",
            );
        }

        return "sqlite:{$database}";
    }

    /**
     * {@inheritDoc}
     */
    protected function afterConnect(): void
    {
        // Activer les clés étrangères
        if (! empty($this->config['foreign_keys'])) {
            $this->pdo->exec($this->enableForeignKeyChecks);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function _listTables(bool $constrainByPrefix = false): string
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";

        if ($constrainByPrefix && $this->getPrefix() !== '') {
            $sql .= " AND name LIKE '" . $this->getPrefix() . "%'";
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function _listIndexes(string $table): array
    {
        $sql = "SELECT 'PRIMARY' as indexname, l.name as fieldname, 'PRIMARY' as indextype
            FROM pragma_table_info(" . $this->escape(strtolower($table)) . ") as l
            WHERE l.pk <> 0
            UNION ALL
            SELECT sqlite_master.name as indexname, ii.name as fieldname,
            CASE
                WHEN ti.pk <> 0 AND sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'PRIMARY'
                WHEN sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'UNIQUE'
                WHEN sqlite_master.sql LIKE '% UNIQUE %' THEN 'UNIQUE'
                ELSE 'INDEX'
                END as indextype
            FROM sqlite_master
            INNER JOIN pragma_index_xinfo(sqlite_master.name) ii ON ii.name IS NOT NULL
            LEFT JOIN pragma_table_info(" . $this->escape(strtolower($table)) . ") ti ON ti.name = ii.name
            WHERE sqlite_master.type='index' AND sqlite_master.tbl_name = " . $this->escape(strtolower($table)) . ' COLLATE NOCASE';

        $rows    = $this->query($sql)->resultObject();
        $indexes = [];

        foreach ($rows as $row) {
            $indexName = $row->indexname;

            if (!isset($indexes[$indexName])) {
                $type = $row->indextype;
                
                $indexes[$indexName] = (object) [
                    'name'    => $indexName,
                    'columns' => [],
                    'type'    => $type,
                    'unique'  => $type === 'UNIQUE',
                    'primary' => $type === 'PRIMARY',
                ];
            }
            
            $indexes[$indexName]->columns[] = $row->fieldname;
        }
    
        return array_values($indexes);
    }

    /**
     * {@inheritDoc}
     */
    public function _listColumns(string $table): array
    {
        $sql = "PRAGMA table_info({$this->escapeIdentifiers($table)})";

        $rows    = $this->query($sql)->resultObject();
        $columns = [];

        foreach ($rows as $row) {
            // SQLite utilise un compteur spécial pour auto_increment
            $autoIncrement = false;
            $generation    = null;
            
            // Vérifier si la colonne est AUTOINCREMENT (via la table sqlite_sequence)
            if ($row->pk && $row->type === 'INTEGER') {
                $seqCheck      = $this->query("SELECT name FROM sqlite_sequence WHERE name = " . $this->escape($table))->resultObject();
                $autoIncrement = !empty($seqCheck);
            }
            
            $typeName = strtolower(explode('(', $row->type)[0] ?? $row->type);
            
            $column                 = new stdClass();
            $column->name           = $row->name;
            $column->type           = $typeName;
            $column->type_name      = $typeName;
            $column->nullable       = !$row->notnull;
            $column->default        = $row->dflt_value;
            $column->auto_increment = $autoIncrement;
            $column->primary_key    = (bool) $row->pk;
            $column->comment        = null;              // SQLite ne supporte pas les commentaires de colonnes nativement
            $column->generation     = $generation;       // SQLite ne supporte pas les colonnes générées avant la version 3.31.0
            
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function _listForeignKeys(string $table): array
    {
        $sql = "PRAGMA foreign_key_list({$table})";

        $rows = $this->query($sql)->resultObject();
        $keys = [];

        foreach ($rows as $row) {
            $key                      = new stdClass();
            $key->constraint_name     = $table . '_' . implode('_', $row->from) . '_foreign';
            $key->table_name          = $table;
            $key->column_name         = $row->from;
            $key->foreign_table_name  = $row->table;
            $key->foreign_column_name = $row->to;
            $key->on_delete           = $row->on_delete;
            $key->on_update           = $row->on_update;
            $key->match               = $row->match;

            $keys[] = $key;
        }

        return $keys;
    }
}
