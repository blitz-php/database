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
use BlitzPHP\Database\Creator\SQLite\Table;
use BlitzPHP\Database\Exceptions\DatabaseException;

/**
 * Createur SQLite
 *
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\SQLite3\Forge</a>
 */
class SQLite extends BaseCreator
{
    /**
     * {@inheritDoc}
     */
    protected string $dropIndexStr = 'DROP INDEX %s';

    /**
     * {@inheritDoc}
     */
    protected BaseConnection $db;

    /**
     * {@inheritDoc}
     */
    protected array|bool $unsigned = false;

    /**
     * {@inheritDoc}
     */
    protected string $null = 'NULL';

    /**
     * {@inheritDoc}
     */
    protected array $mapTypes = [
        'boolean' => ['TINYINT', 1],

        'bigInteger'    => 'INTEGER',
        'integer'       => 'INTEGER',
        'mediumInteger' => 'INTEGER',
        'smallInteger'  => 'INTEGER',
        'tinyInteger'   => 'INTEGER',
        'decimal'       => 'NUMERIC',
        'double'        => 'FLOAT',
        'float'         => 'FLOAT',

        'date'        => 'DATE',
        'dateTime'    => 'DATETIME',
        'dateTimeTz'  => 'DATETIME',
        'time'        => 'TIME',
        'timeTz'      => 'TIME',
        'timestamp'   => 'DATETIME',
        'timestampTz' => 'DATETIME',
        'year'        => 'INTEGER',

        'binary'     => 'BLOB',
        'char'       => 'VARCHAR',
        'longText'   => 'TEXT',
        'mediumText' => 'TEXT',
        'string'     => 'VARCHAR',
        'text'       => 'TEXT',

        'uuid'       => ['VARCHAR', 36],
        'ipAddress'  => ['VARCHAR', 45],
        'macAddress' => ['VARCHAR', 17],

        'enum' => 'VARCHAR CHECK ({column} in ({allowed}))',
        'set'  => false,

        'json'  => 'TEXT',
        'jsonb' => 'TEXT',

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
     * Constructor.
     */
    public function __construct(BaseConnection $db)
    {
        parent::__construct($db);

        if (version_compare($this->db->getVersion(), '3.3', '<')) {
            $this->dropTableIfStr = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createDatabase(string $dbName, bool $ifNotExists = false): bool
    {
        // In SQLite, a database is created when you connect to the database.
        // We'll return TRUE so that an error isn't generated.
        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException
     */
    public function dropDatabase(string $dbName): bool
    {
        // In SQLite, a database is dropped when we delete a file
        if (! is_file($dbName)) {
            if ($this->db->debug) {
                throw new DatabaseException('Impossible de supprimer la base de données spécifiée.');
            }

            return false;
        }

        // We need to close the pseudo-connection first
        $this->db->close();
        if (! @unlink($dbName)) {
            if ($this->db->debug) {
                throw new DatabaseException('Impossible de supprimer la base de données spécifiée.');
            }

            return false;
        }

        if (! empty($this->db->dataCache['db_names'])) {
            $key = array_search(strtolower($dbName), array_map('strtolower', $this->db->dataCache['db_names']), true);
            if ($key !== false) {
                unset($this->db->dataCache['db_names'][$key]);
            }
        }

        return true;
    }

    /**
     * @param array|string $field
     *
     * @return array|string|null
     */
    protected function _alterTable(string $alterType, string $table, $field)
    {
        switch ($alterType) {
            case 'DROP':
                $sqlTable = new Table($this->db, $this);

                $sqlTable->fromTable($table)
                    ->dropColumn($field)
                    ->run();

                return '';

            case 'CHANGE':
                (new Table($this->db, $this))
                    ->fromTable($table)
                    ->modifyColumn($field)
                    ->run();

                return null;

            default:
                return parent::_alterTable($alterType, $table, $field);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function _processColumn(array $field): string
    {
        if ($field['type'] === 'TEXT' && str_starts_with($field['length'], "('")) {
            $field['type'] .= ' CHECK(' . $this->db->escapeIdentifiers($field['name'])
                . ' IN ' . $field['length'] . ')';
        }

        return $this->db->escapeIdentifiers($field['name'])
            . ' ' . $field['type']
            . $field['auto_increment']
            . $field['null']
            . $field['unique']
            . $field['default'];
    }

    /**
     * {@inheritDoc}
     */
    protected function _attributeType(array &$attributes)
    {
        switch (strtoupper($attributes['TYPE'])) {
            case 'ENUM':
            case 'SET':
                $attributes['TYPE'] = 'TEXT';
                break;

            case 'BOOLEAN':
                $attributes['TYPE'] = 'INT';
                break;

            default:
                break;
        }
    }

    /**
     *{@inheritDoc}
     */
    protected function _attributeAutoIncrement(array &$attributes, array &$field)
    {
        if (! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true
            && stripos($field['type'], 'int') !== false) {
            $field['type']           = 'INTEGER PRIMARY KEY';
            $field['default']        = '';
            $field['null']           = '';
            $field['unique']         = '';
            $field['auto_increment'] = ' AUTOINCREMENT';

            $this->primaryKeys = [];
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException
     */
    public function dropForeignKey(string $table, string $foreignName): bool
    {
        // If this version of SQLite doesn't support it, we're done here
        if ($this->db->supportsForeignKeys() !== true) {
            return true;
        }

        // Otherwise we have to copy the table and recreate
        // without the foreign key being involved now
        $sqlTable = new Table($this->db, $this);

        return $sqlTable->fromTable($this->db->prefix . $table)
            ->dropForeignKey($foreignName)
            ->run();
    }

    /**
     * {@inheritDoc}
     */
    public function dropPrimaryKey(string $table, string $keyName = ''): bool
    {
        $sqlTable = new Table($this->db, $this);

        return $sqlTable->fromTable($this->db->prefix . $table)
            ->dropPrimaryKey()
            ->run();
    }

    /**
     * {@inheritDoc}
     */
    public function addForeignKey(array|string $fieldName = '', string $tableName = '', array|string $tableField = '', string $onUpdate = '', string $onDelete = '', string $fkName = ''): self
    {
        $fkName = '';
        if ($fkName === '') {
            return parent::addForeignKey($fieldName, $tableName, $tableField, $onUpdate, $onDelete, $fkName);
        }

        throw new DatabaseException('SQLite does not support foreign key names. BlitzPHP will refer to them in the format: prefix_table_column_referencecolumn_foreign');
    }

    /**
     * {@inheritDoc}
     */
    protected function _processPrimaryKeys(string $table, bool $asQuery = false): string
    {
        if ($asQuery === false) {
            return parent::_processPrimaryKeys($table, $asQuery);
        }

        $sqlTable = new Table($this->db, $this);

        $sqlTable->fromTable($this->db->prefix . $table)
            ->addPrimaryKey($this->primaryKeys)
            ->run();

        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _processForeignKeys(string $table, bool $asQuery = false): array
    {
        if ($asQuery === false) {
            return parent::_processForeignKeys($table, $asQuery);
        }

        $errorNames = [];

        foreach ($this->foreignKeys as $name) {
            foreach ($name['field'] as $f) {
                if (! isset($this->fields[$f])) {
                    $errorNames[] = $f;
                }
            }
        }

        if ($errorNames !== []) {
            $errorNames = implode(', ', $errorNames);

            throw new DatabaseException('Champs "' . $errorNames . '" non trouvés');
        }

        $sqlTable = new Table($this->db, $this);

        $sqlTable->fromTable($this->db->prefix . $table)
            ->addForeignKey($this->foreignKeys)
            ->run();

        return [];
    }
}
