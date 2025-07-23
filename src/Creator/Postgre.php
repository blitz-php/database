<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Creator;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Connection\Postgre as ConnectionPostgre;

/**
 * Createur Postgre
 *
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\Postgre\Forge</a>
 */
class Postgre extends BaseCreator
{
    /**
     * {@inheritDoc}
     */
    protected string $checkDatabaseExistStr = 'SELECT 1 FROM pg_database WHERE datname = ?';

    /**
     * {@inheritDoc}
     */
    protected string $dropConstraintStr = 'ALTER TABLE %s DROP CONSTRAINT %s';

    /**
     * {@inheritDoc}
     */
    protected string $dropIndexStr = 'DROP INDEX %s';

    /**
     * {@inheritDoc}
     */
    protected array|bool $unsigned = [
        'INT2'     => 'INTEGER',
        'SMALLINT' => 'INTEGER',
        'INT'      => 'BIGINT',
        'INT4'     => 'BIGINT',
        'INTEGER'  => 'BIGINT',
        'INT8'     => 'NUMERIC',
        'BIGINT'   => 'NUMERIC',
        'REAL'     => 'DOUBLE PRECISION',
        'FLOAT'    => 'DOUBLE PRECISION',
    ];

    /**
     * {@inheritDoc}
     */
    protected string $null = 'NULL';

    /**
     * {@inheritDoc}
     * 
     * @var ConnectionPostgre
     */
    protected BaseConnection $db;

    /**
     * {@inheritDoc}
     */
    protected array $mapTypes = [
        'boolean' => 'BOOLEAN',

        'bigInteger'    => 'BIGINT|BIGSERIAL',
        'integer'       => 'INTEGER|SERIAL',
        'mediumInteger' => 'INTEGER|SERIAL',
        'smallInteger'  => 'SMALLINT|SMALLSERIAL',
        'tinyInteger'   => 'SMALLINT|SMALLSERIAL',
        'decimal'       => 'DECIMAL',
        'double'        => 'DOUBLE PRECISION',
        'float'         => 'DOUBLE PRECISION',
        'real'          => 'REAL',

        'date'        => 'DATE',
        'dateTime'    => 'TIMESTAMP({precision}) WITHOUT TIME ZONE',
        'dateTimeTz'  => 'TIMESTAMP({precision}) WITH TIME ZONE',
        'time'        => 'TIME({precision}) WITHOUT TIME ZONE',
        'timeTz'      => 'TIME({precision}) WITH TIME ZONE',
        'timestamp'   => 'TIMESTAMP({precision}) WITHOUT TIME ZONE',
        'timestampTz' => 'TIMESTAMP({precision}) WITH TIME ZONE',
        'year'        => 'INTEGER',

        'binary'     => 'BYTEA',
        'char'       => 'CHAR',
        'longText'   => 'TEXT',
        'mediumText' => 'TEXT',
        'string'     => 'VARCHAR',
        'text'       => 'TEXT',

        'uuid'       => 'UUID',
        'ipAddress'  => 'INET',
        'macAddress' => 'MACADDR',

        'enum' => ['VARCHAR', 255],

        'json'  => 'JSON',
        'jsonb' => 'JSONB',

        'geometry'           => 'GEOGRAPHY(GEOMETRY, 4326)',
        'geometryCollection' => 'GEOGRAPHY(GEOMETRYCOLLECTION, 4326)',
        'lineString'         => 'GEOGRAPHY(LINESTRING, 4326)',
        'multiLineString'    => 'GEOGRAPHY(MULTILINESTRING, 4326)',
        'multiPoint'         => 'GEOGRAPHY(MULTIPOINT, 4326)',
        'multiPolygon'       => 'GEOGRAPHY(MULTIPOLYGON, 4326)',
        'multiPolygonZ'      => 'GEOGRAPHY(MULTIPOLYGONZ, 4326)',
        'point'              => 'GEOGRAPHY(POINT, 4326)',
        'polygon'            => 'GEOGRAPHY(POLYGON, 4326)',

        'computed' => false, // Non supporter
    ];

    /**
     * {@inheritDoc}
     */
    protected function _createTableAttributes(array $attributes): string
    {
        return '';
    }

    /**
     * @return array|bool|string
     */
    protected function _alterTable(string $alterType, string $table, array|string $processedFields)
    {
        if (in_array($alterType, ['DROP', 'ADD'], true)) {
            return parent::_alterTable($alterType, $table, $processedFields);
        }

        $sql  = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table);
        $sqls = [];

        foreach ($processedFields as $field) {
            if ($field['_literal'] !== false) {
                return false;
            }

            if (version_compare($this->db->getVersion(), '8', '>=') && isset($field['type'])) {
                $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escapeIdentifiers($field['name'])
                    . " TYPE {$field['type']}{$field['length']}";
            }

            if (! empty($data['default'])) {
                $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escapeIdentifiers($field['name'])
                    . " SET DEFAULT {$field['default']}";
            }

            $nullable = true; // Nullable par defaut.
            if (isset($field['null']) && ($field['null'] === false || $field['null'] === ' NOT ' . $this->null)) {
                $nullable = false;
            }
            $sqls[] = $sql . ' ALTER COLUMN ' . $this->db->escapeIdentifiers($field['name'])
                . ($nullable ? ' DROP' : ' SET') . ' NOT NULL';

            if (! empty($field['new_name'])) {
                $sqls[] = $sql . ' RENAME COLUMN ' . $this->db->escapeIdentifiers($field['name'])
                    . ' TO ' . $this->db->escapeIdentifiers($field['new_name']);
            }

            if (! empty($data['comment'])) {
                $sqls[] = 'COMMENT ON COLUMN' . $this->db->escapeIdentifiers($table)
                    . '.' . $this->db->escapeIdentifiers($field['name'])
                    . " IS {$field['comment']}";
            }
        }

        return $sqls;
    }

    /**
     * {@inheritDoc}
     */
    protected function _processColumn(array $processedField): string
    {
        return $this->db->escapeIdentifiers($processedField['name'])
            . ' ' . $processedField['type'] . ($processedField['type'] === 'text' ? '' : $processedField['length'])
            . $processedField['default']
            . $processedField['null']
            . $processedField['auto_increment']
            . $processedField['unique'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _attributeType(array &$attributes)
    {
        // Reinitialise la longueur du champ pour les types de données qui ne les supporte pas
        if (isset($attributes['CONSTRAINT']) && str_contains(strtolower($attributes['TYPE']), 'int')) {
            $attributes['CONSTRAINT'] = null;
        }

        switch (strtoupper($attributes['TYPE'])) {
            case 'TINYINT':
                $attributes['TYPE']     = 'SMALLINT';
                $attributes['UNSIGNED'] = false;
                break;

            case 'MEDIUMINT':
                $attributes['TYPE']     = 'INTEGER';
                $attributes['UNSIGNED'] = false;
                break;

            case 'DATETIME':
                $attributes['TYPE'] = 'TIMESTAMP';
                break;

            case 'BLOB':
                $attributes['TYPE'] = 'BYTEA';
                break;

            default:
                break;
        }
    }

    /**
     * Attributs de champs AUTO_INCREMENT
     */
    protected function _attributeAutoIncrement(array &$attributes, array &$field)
    {
        if (! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true) {
            $field['type'] = $field['type'] === 'NUMERIC' || $field['type'] === 'BIGINT' ? 'BIGSERIAL' : 'SERIAL';
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function _dropTable(string $table, bool $ifExists, bool $cascade): string
    {
        $sql = parent::_dropTable($table, $ifExists, $cascade);

        if ($cascade === true) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function _dropKeyAsConstraint(string $table, string $constraintName): string
    {
        return "SELECT con.conname
               FROM pg_catalog.pg_constraint con
                INNER JOIN pg_catalog.pg_class rel
                           ON rel.oid = con.conrelid
                INNER JOIN pg_catalog.pg_namespace nsp
                           ON nsp.oid = connamespace
               WHERE nsp.nspname = '{$this->db->schema}'
                     AND rel.relname = '" . trim($table, '"') . "'
                     AND con.conname = '" . trim($constraintName, '"') . "'";
    }
}
