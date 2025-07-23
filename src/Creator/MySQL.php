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

/**
 * Createur MySQL
 *
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\MySQLi\Forge</a>
 */
class MySQL extends BaseCreator
{
    /**
     * {@inheritDoc}
     *
     * @var string
     */
    protected $createDatabaseStr = 'CREATE DATABASE %s CHARACTER SET %s COLLATE %s';

    /**
     * {@inheritDoc}
     */
    protected string $createDatabaseIfStr = 'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s';

    /**
     * {@inheritDoc}
     */
    protected string $dropConstraintStr = 'ALTER TABLE %s DROP FOREIGN KEY %s';

    /**
     * {@inheritDoc}
     */
    protected bool $createTableKeys = true;

    /**
     * {@inheritDoc}
     */
    protected array|bool $unsigned = [
        'TINYINT',
        'SMALLINT',
        'MEDIUMINT',
        'INT',
        'INTEGER',
        'BIGINT',
        'REAL',
        'DOUBLE',
        'DOUBLE PRECISION',
        'FLOAT',
        'DECIMAL',
        'NUMERIC',
    ];

    /**
     * Table Options list which required to be quoted
     */
    protected array $_quoted_table_options = [
        'COMMENT',
        'COMPRESSION',
        'CONNECTION',
        'DATA DIRECTORY',
        'INDEX DIRECTORY',
        'ENCRYPTION',
        'PASSWORD',
    ];

    /**
     * {@inheritDoc}
     */
    protected string $null = 'NULL';

    /**
     * {@inheritDoc}
     */
    protected array $mapTypes = [
        'boolean' => ['TINYINT', 1],

        'bigInteger'    => 'BIGINT',
        'integer'       => 'INT',
        'mediumInteger' => 'MEDIUMINT',
        'smallInteger'  => 'SMALLINT',
        'tinyInteger'   => 'TINYINT',
        'decimal'       => 'DECIMAL',
        'double'        => 'DOUBLE',
        'float'         => 'DOUBLE',

        'date'        => 'DATE',
        'dateTime'    => 'DATETIME',
        'dateTimeTz'  => 'DATETIME',
        'time'        => 'TIME',
        'timeTz'      => 'TIME',
        'timestamp'   => 'TIMESTAMP',
        'timestampTz' => 'TIMESTAMP',
        'year'        => 'YEAR',

        'binary'     => 'BLOB',
        'char'       => 'CHAR',
        'longText'   => 'LONGTEXT',
        'mediumText' => 'MEDIUMTEXT',
        'string'     => 'VARCHAR',
        'text'       => 'TEXT',

        'uuid'       => ['VARCHAR', 36],
        'ipAddress'  => ['VARCHAR', 45],
        'macAddress' => ['VARCHAR', 17],

        'enum' => 'ENUM',
        'set'  => 'SET',

        'json'  => 'JSON',
        'jsonb' => 'JSON',

        'geometry'           => 'GEOMETRY',
        'geometryCollection' => 'GEOMETRYCOLLECTION',
        'lineString'         => 'LINESTRING',
        'multiLineString'    => 'MULTILINESTRING',
        'multiPoint'         => 'MULTIPOINT',
        'multiPolygon'       => 'MULTIPOLYGON',
        'point'              => 'POINT',
        'polygon'            => 'POLYGON',

        'computed' => false, // Non supporter
    ];

    /**
     * attributs pour CREATE TABLE.
     *
     * @param array $attributes tableau associatif des attributs de la table
     */
    protected function _createTableAttributes(array $attributes): string
    {
        $sql = '';

        foreach (array_keys($attributes) as $key) {
            if (is_string($key)) {
                $sql .= ' ' . strtoupper($key) . ' = ';

                if (in_array(strtoupper($key), $this->_quoted_table_options, true)) {
                    $sql .= $this->db->escape($attributes[$key]);
                } else {
                    $sql .= $this->db->escapeString($attributes[$key]);
                }
            }
        }

        if (! empty($this->db->charset) && ! strpos($sql, 'CHARACTER SET') && ! strpos($sql, 'CHARSET')) {
            $sql .= ' DEFAULT CHARACTER SET = ' . $this->db->escapeString($this->db->charset);
        }

        if (! empty($this->db->collation) && ! strpos($sql, 'COLLATE')) {
            $sql .= ' COLLATE = ' . $this->db->escapeString($this->db->collation);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function _alterTable(string $alterType, string $table, array|string $processedFields)
    {
        if ($alterType === 'DROP') {
            return parent::_alterTable($alterType, $table, $processedFields);
        }

        $sql = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table);

        foreach ($processedFields as $i => $field) {
            if ($field['_literal'] !== false) {
                $processedFields[$i] = ($alterType === 'ADD') ? "\n\tADD " . $field['_literal'] : "\n\tMODIFY " . $field['_literal'];
            } else {
                if ($alterType === 'ADD') {
                    $processedFields[$i]['_literal'] = "\n\tADD ";
                } else {
                    $processedFields[$i]['_literal'] = empty($field['new_name']) ? "\n\tMODIFY " : "\n\tCHANGE ";
                }

                $processedFields[$i] = $processedFields[$i]['_literal'] . $this->_processColumn($processedFields[$i]);
            }
        }

        return [$sql . implode(',', $processedFields)];
    }

    /**
     * {@inheritDoc}
     */
    protected function _processColumn(array $processedField): string
    {
        $extraClause = isset($processedField['after']) ? ' AFTER ' . $this->db->escapeIdentifiers($processedField['after']) : '';

        if (empty($extraClause) && isset($processedField['first']) && $processedField['first'] === true) {
            $extraClause = ' FIRST';
        }

        return $this->db->escapeIdentifiers($processedField['name'])
                . (empty($field['new_name']) ? '' : ' ' . $this->db->escapeIdentifiers($processedField['new_name']))
                . ' ' . $processedField['type'] . $processedField['length']
                . $processedField['unsigned']
                . $processedField['null']
                . $processedField['default']
                . $processedField['auto_increment']
                . $processedField['unique']
                . (empty($field['comment']) ? '' : ' COMMENT ' . $processedField['comment'])
                . $extraClause;
    }

    /**
     * {@inheritDoc}
     */
    protected function _processIndexes(string $table, bool $asQuery = false): array
    {
        $sqls  = [''];
        $index = 0;

        for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
            $index = $i;
            if ($asQuery === false) {
                $index = 0;
            }

            if (isset($this->keys[$i]['fields'])) {
                for ($i2 = 0, $c2 = count($this->keys[$i]['fields']); $i2 < $c2; $i2++) {
                    if (! isset($this->fields[$this->keys[$i]['fields'][$i2]])) {
                        unset($this->keys[$i]['fields'][$i2]);

                        continue;
                    }
                }
            }

            if (! is_array($this->keys[$i]['fields'])) {
                $this->keys[$i]['fields'] = [$this->keys[$i]['fields']];
            }

            $unique = in_array($i, $this->uniqueKeys, true) ? 'UNIQUE ' : '';

            $keyName = $this->db->escapeIdentifiers(($this->keys[$i]['keyName'] === '') ?
                implode('_', $this->keys[$i]['fields']) :
                $this->keys[$i]['keyName']);

            if ($asQuery === true) {
                $sqls[$index] = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table) . " ADD {$unique}KEY "
                    . $keyName
                    . ' (' . implode(', ', $this->db->escapeIdentifiers($this->keys[$i]['fields'])) . ')';
            } else {
                $sqls[$index] .= ",\n\t{$unique}KEY " . $keyName
                . ' (' . implode(', ', $this->db->escapeIdentifiers($this->keys[$i]['fields'])) . ')';
            }
        }

        $this->keys = [];

        return $sqls;
    }

    /**
     * {@inheritDoc}
     */
    public function dropKey(string $table, string $keyName, bool $prefixKeyName = true): bool
    {
        $sql = sprintf(
            $this->dropIndexStr,
            $this->db->escapeIdentifiers($keyName),
            $this->db->escapeIdentifiers($this->db->prefix . $table),
        );

        return $this->db->query($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function dropPrimaryKey(string $table, string $keyName = ''): bool
    {
        $sql = sprintf(
            'ALTER TABLE %s DROP PRIMARY KEY',
            $this->db->escapeIdentifiers($this->db->prefix . $table)
        );

        return $this->db->query($sql);
    }
}
