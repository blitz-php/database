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
use BlitzPHP\Database\Connection\SQLite as SQLiteConnection;
use BlitzPHP\Database\Creator\SQLite\Table;
use BlitzPHP\Database\Exceptions\CreatorException;
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
     *
     * @var SQLiteConnection
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
        'char'       => 'TEXT',
        'longText'   => 'TEXT',
        'mediumText' => 'TEXT',
        'string'     => 'TEXT',
        'text'       => 'TEXT',

        'uuid'       => ['TEXT', 36],
        'ipAddress'  => ['TEXT', 45],
        'macAddress' => ['TEXT', 17],

        'enum' => 'TEXT CHECK ({column} in ({allowed}))',
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
        // Dans SQLite, une bd est effacée quand on supprime un fichier
        if (! is_file($dbName)) {
            if ($this->debug) {
                throw CreatorException::unableToDropDatabase($dbName);
            }

            return false;
        }

        // Nous devons d'abord fermer la pseudo-connexion
        $this->db->close();

        if (! @unlink($dbName)) {
            if ($this->debug) {
                throw CreatorException::unableToDropDatabase($dbName);
            }

            return false;
        }

        if (! empty($this->dataCache['db_names'])) {
            $key = array_search(strtolower($dbName), array_map('strtolower', $this->dataCache['db_names']), true);
            if ($key !== false) {
                unset($this->dataCache['db_names'][$key]);
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param list<string>|string $columnNames
     *
     * @throws DatabaseException
     */
    public function dropColumn(string $table, $columnNames): bool
    {
        $columns = is_array($columnNames) ? $columnNames : array_map(trim(...), explode(',', $columnNames));

        $result = (new Table($this->db, $this))
            ->fromTable($this->prefix . $table)
            ->dropColumn($columns)
            ->run();

        if (! $result && $this->debug) {
            throw new CreatorException(sprintf(
                'Failed to drop column%s "%s" on "%s" table.',
                count($columns) > 1 ? 's' : '',
                implode('", "', $columns),
                $table,
            ));
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function _alterTable(string $alterType, string $table, $processedFields)
    {
        switch ($alterType) {
            case 'CHANGE':
                $fieldsToModify = [];

                foreach ($processedFields as $processedField) {
                    $name    = $processedField['name'];
                    $newName = $processedField['new_name'];

                    $field             = $this->fields[$name];
                    $field['name']     = $name;
                    $field['new_name'] = $newName;

                    // Supprime lorsqu'on crée une table, si `null` c'est que ce n'est pas specifié,
                    // le champ sera `NULL`, pas `NOT NULL`.
                    if ($processedField['null'] === '') {
                        $field['null'] = true;
                    }

                    $fieldsToModify[] = $field;
                }

                (new Table($this->db, $this))
                    ->fromTable($table)
                    ->modifyColumn($fieldsToModify)
                    ->run();

                return null;

            default:
                return parent::_alterTable($alterType, $table, $processedFields);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function _processColumn(array $processedField): string
    {
        $column = $this->db->escapeIdentifiers($processedField['name']);

        if ($processedField['type'] === 'TEXT') {
            // Retirer les parenthèses autour de la contrainte
            $constraint = trim($processedField['length'], '()');
    
            if (str_starts_with($constraint, "'")) {
                // Cas énumération : ('A','B','C')
                $processedField['type'] .= ' CHECK(' . $column . ' IN (' . $constraint . '))';
            } elseif (ctype_digit($constraint) && (int)$constraint > 0) {
                // Cas longueur numérique : (255)
                $processedField['type'] .= ' CHECK(length(' . $column . ') <= ' . (int)$constraint . ')';
            }
        }    

        return $column
            . ' ' . $processedField['type']
            . $processedField['auto_increment']
            . $processedField['null']
            . $processedField['unique']
            . $processedField['default'];
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
        // Si cette version de SQLite ne le prend pas en charge, nous en avons terminé ici
        if ($this->db->supportsForeignKeys() !== true) {
            return true;
        }

        // Sinon, nous devons copier la table et la recréer sans que la clé étrangère ne soit impliquée.
        $sqlTable = new Table($this->db, $this);

        return $sqlTable->fromTable($this->prefix . $table)
            ->dropForeignKey($foreignName)
            ->run();
    }

    /**
     * {@inheritDoc}
     */
    public function dropPrimaryKey(string $table, string $keyName = ''): bool
    {
        $sqlTable = new Table($this->db, $this);

        return $sqlTable->fromTable($this->prefix . $table)
            ->dropPrimaryKey()
            ->run();
    }

    /**
     * {@inheritDoc}
     */
    public function addForeignKey(array|string $fieldName = '', string $tableName = '', array|string $tableField = '', string $onUpdate = '', string $onDelete = '', string $fkName = ''): static
    {
        $fkName = '';
        if ($fkName === '') {
            return parent::addForeignKey($fieldName, $tableName, $tableField, $onUpdate, $onDelete, $fkName);
        }

        throw new CreatorException('SQLite ne supporte pas les noms de clés étrangères. BlitzPHP se referera à sa suivant le format: prefix_table_column_referencecolumn_foreign');
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

        $sqlTable->fromTable($this->prefix . $table)
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

        $sqlTable->fromTable($this->prefix . $table)
            ->addForeignKey($this->foreignKeys)
            ->run();

        return [];
    }
}
