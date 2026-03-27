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
use PDOException;
use stdClass;

/**
 * Connexion MySQL
 */
class MySQL extends BaseConnection
{
    /**
     * Caractère d'échappement MySQL
     */
    protected string $escapeChar = '`';

    /**
     * {@inheritDoc}
     */
    protected string $disableForeignKeyChecks = 'SET FOREIGN_KEY_CHECKS = 0';

    /**
     * {@inheritDoc}
     */
    protected string $enableForeignKeyChecks = 'SET FOREIGN_KEY_CHECKS = 1';

    /**
     * {@inheritDoc}
     */
    protected function getDsn(): string
    {
        if (! empty($this->config['dsn'])) {
            return $this->config['dsn'];
        }

        $dsn = "mysql:host={$this->config['hostname']}";

        if (! empty($this->config['port'])) {
            $dsn .= ";port={$this->config['port']}";
        }

        if (! empty($this->config['database'])) {
            $dsn .= ";dbname={$this->config['database']}";
        }

        if (! empty($this->config['charset'])) {
            $dsn .= ";charset={$this->config['charset']}";
        }

        return $dsn;
    }

    /**
     * {@inheritDoc}
     */
    protected function afterConnect(): void
    {
        // Configuration du charset
        if (! empty($this->config['charset'])) {
            $statement = "SET NAMES '{$this->config['charset']}'";

            if (! empty($this->config['collation'])) {
                $statement .= " COLLATE '{$this->config['collation']}'";
            }

            $this->pdo->exec($statement);
        }

        // Mode strict
        if (isset($this->config['strict_on']) && $this->config['strict_on'] === true) {
            $this->pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES'");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setDatabase(string $databaseName): bool
    {
        try {
            $this->pdo->exec("USE {$this->escapeIdentifiers($databaseName)}");
            $this->config['database'] = $databaseName;

            return true;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Impossible de sélectionner la base de données : ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function _listTables(bool $constrainByPrefix = false): string
    {
        $sql = "SHOW TABLES FROM `{$this->getDatabase()}`";

        if ($constrainByPrefix && $this->getPrefix() !== '') {
            $sql .= " LIKE '" . $this->getPrefix() . "%'";
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function _listIndexes(string $table): array
    {
        $sql = "SHOW INDEX FROM {$this->escapeIdentifiers($table)}";

        $rows    = $this->query($sql)->resultObject();
        $indexes = [];

        foreach ($rows as $row) {
            $indexName = $row->Key_name;
            
            if (! isset($indexes[$indexName])) {
                $type = match (true) {
                    $indexName       === 'PRIMARY'                         => 'PRIMARY',
                    $row->Index_type === 'FULLTEXT'                        => 'FULLTEXT',
                    $row->Index_type === 'SPATIAL'                         => 'SPATIAL',
                    isset($row->Non_unique) && (int)$row->Non_unique === 0 => 'UNIQUE',
                                     default                               => 'INDEX',
                };
                
                $indexes[$indexName] = (object) [
                    'name'    => $indexName,
                    'columns' => [],
                    'type'    => $type,
                    'unique'  => $type === 'UNIQUE',
                    'primary' => $type === 'PRIMARY',
                ];
            }
            
            $indexes[$indexName]->columns[] = $row->Column_name;
        }
        
        return array_values($indexes);
    }

    /**
     * {@inheritDoc}
     */
    public function _listColumns(string $table): array
    {
        $sql = "SHOW FULL COLUMNS FROM {$this->escapeIdentifiers($table)}";

        $rows    = $this->query($sql)->resultObject();
        $columns = [];

        foreach ($rows as $row) {
            $typeName = $row->Type;
            $maxLength = null;
            
            if (preg_match('/^([a-z]+)(?:\((\d+)\))?/', $row->Type, $matches)) {
                $typeName  = $matches[1];
                $maxLength = isset($matches[2]) ? (int) $matches[2] : null;
            }
            
            $autoIncrement = stripos($row->Extra, 'auto_increment') !== false;
            
            // Gérer la génération (virtuelle/stored)
            $generation = null;
            if (stripos($row->Extra, 'GENERATED') !== false) {
                $generation = [
                    'type'       => stripos($row->Extra, 'VIRTUAL') !== false ? 'VIRTUAL' : 'STORED',
                    'expression' => null, // MySQL ne fournit pas l'expression via SHOW COLUMNS
                ];
            }
            
            $column                 = new stdClass();
            $column->name           = $row->Field;
            $column->type           = $typeName;
            $column->type_name      = $typeName;
            $column->nullable       = $row->Null === 'YES';
            $column->default        = $row->Default;
            $column->auto_increment = $autoIncrement;
            $column->max_length     = $maxLength;
            $column->comment        = $row->Comment ?: null;
            $column->primary_key    = $row->Key  === 'PRI';
            $column->generation     = $generation;
            
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function _listForeignKeys(string $table): array
    {
        $sql = '
            SELECT
                tc.CONSTRAINT_NAME,
                tc.TABLE_NAME,
                kcu.COLUMN_NAME,
                rc.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM information_schema.TABLE_CONSTRAINTS AS tc
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
                ON tc.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
            INNER JOIN information_schema.KEY_COLUMN_USAGE AS kcu
                ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE
                tc.CONSTRAINT_TYPE = ' . $this->escape('FOREIGN KEY') . ' AND
                tc.TABLE_SCHEMA = ' . $this->escape($this->getDatabase()) . ' AND
                tc.TABLE_NAME = ' . $this->escape($this->prefixTable($table));

        $rows = $this->query($sql)->resultObject();
        $keys = [];

        foreach ($rows as $row) {
            $key                      = new stdClass();
            $key->constraint_name     = $row->CONSTRAINT_NAME;
            $key->table_name          = $row->TABLE_NAME;
            $key->column_name         = $row->COLUMN_NAME;
            $key->foreign_table_name  = $row->REFERENCED_TABLE_NAME;
            $key->foreign_column_name = $row->REFERENCED_COLUMN_NAME;

            $keys[] = $key;
        }

        return $keys;
    }
}
