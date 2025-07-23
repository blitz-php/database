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

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Query;
use BlitzPHP\Database\RawSql;
use BlitzPHP\Database\Result\BaseResult;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * La classe creator transforme les migrations en requete SQL executable.
 *
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\Forge</a>
 */
class BaseCreator
{
    /**
     * La connexion a la base de donnees
     */
    protected BaseConnection $db;

    /**
     * Liste des champs sous la forme `[name => attributes]`.
     *
     * @var array<string, array<string, bool|string>|string>
     */
    protected array $fields = [];

    /**
     * Liste des cles.
     *
     * @var list<array{fields?: list<string>, keyName?: string}>
     */
    protected array $keys = [];

    /**
     * Liste des cles uniques.
     */
    protected array $uniqueKeys = [];

    /**
     * cles primaires.
     *
     * @var array{fields?: list<string>, keyName?: string}
     */
    protected array $primaryKeys = [];

    /**
     * Liste des cles etrangeres.
     */
    protected array $foreignKeys = [];

    /**
     * Character set utilisee.
     */
    protected string $charset = '';

    /**
     * requete CREATE DATABASE.
     *
     * @var false|string
     */
    protected $createDatabaseStr = 'CREATE DATABASE %s';

    /**
     * requete CREATE DATABASE IF
     */
    protected string $createDatabaseIfStr;

    /**
     * requete CHECK DATABASE EXIST.
     */
    protected string $checkDatabaseExistStr;

    /**
     * requete DROP DATABASE.
     *
     * @var false|string
     */
    protected $dropDatabaseStr = 'DROP DATABASE %s';

    /**
     * requete CREATE TABLE
     */
    protected string $createTableStr = "%s %s (%s\n)";

    /**
     * drapeau des clés CREATE TABLE
     *
     * Indique si les clés de table sont créées à partir de l'instruction CREATE TABLE.
     */
    protected bool $createTableKeys = false;

    /**
     * requete DROP TABLE IF EXISTS
     *
     * @var bool|string
     */
    protected $dropTableIfStr = 'DROP TABLE IF EXISTS';

    /**
     * requete RENAME TABLE
     *
     * @var false|string
     */
    protected $renameTableStr = 'ALTER TABLE %s RENAME TO %s';

    /**
     * support UNSIGNED
     */
    protected array|bool $unsigned = true;

    /**
     * Representation de la valeur NULL dans les requetes CREATE/ALTER TABLE
     *
     * @internal Utilisee pour faire les champs nullable.
     */
    protected string $null = 'NULL';

    /**
     * Representation de la valeur par defaut dans les requetes CREATE/ALTER TABLE
     *
     * @var false|string
     */
    protected $default = ' DEFAULT ';

    /**
     * requete DROP CONSTRAINT
     */
    protected string $dropConstraintStr;

    /**
     * requete DROP INDEX
     */
    protected string $dropIndexStr = 'DROP INDEX %s ON %s';

    /**
     * Actions autorisees pour les cles etrangeres
     */
    protected array $fkAllowActions = ['CASCADE', 'SET NULL', 'NO ACTION', 'RESTRICT', 'SET DEFAULT'];

    /**
     * Table des correspondances des types de donnees
     *
     * @var array<string, array|false|string>
     *
     * @example
     * ```
     * [
     *      'type_objet' =>  ['type_sql', taille], // si on doit donner avec une taille par défaut
     *      'type_objet' =>  'type_sql', // si la taille n'est pas necessaire
     *      'type_objet' =>  false, // si le pilote de la base de données ne supporte pas ce type de données
     * ]
     * ```
     */
    protected array $mapTypes = [];

    /**
     * Constructor.
     */
    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Fournit un acces a l'actuelle connexion a la base de donnees.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->db;
    }

    /**
     * Recupere le type de champs en fonction de la base de donnees
     *
     * @return array|string
     */
    public function typeOf(?string $type = null)
    {
        if ($type === null) {
            return $this->mapTypes;
        }

        $type = $this->mapTypes[$type] ?? null;

        if ($type === null) {
            throw new UnexpectedValueException('Type de donnee non reconnu : ' . $type);
        }

        if ($type === false) {
            throw new RuntimeException('Ce pilode de base de donnees requiet un type, voir les modificateurs virtualAs / storedAs.');
        }

        return $type;
    }

    /**
     * Creation de la base de donnees
     *
     * @param bool $ifNotExists Specifie si on doit ajouter la condition IF NOT EXISTS
     *
     * @throws DatabaseException
     */
    public function createDatabase(string $dbName, bool $ifNotExists = false): bool
    {
        if ($ifNotExists && $this->createDatabaseIfStr === null) {
            if ($this->databaseExists($dbName)) {
                return true;
            }

            $ifNotExists = false;
        }

        if ($this->createDatabaseStr === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonctionnalité n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false; // @codeCoverageIgnore
        }

        try {
            if (! $this->db->query(
                sprintf(
                    $ifNotExists ? $this->createDatabaseIfStr : $this->createDatabaseStr,
                    $this->db->escapeIdentifier($dbName),
                    $this->db->charset,
                    $this->db->collation
                )
            )) {
                // @codeCoverageIgnoreStart
                if ($this->db->debug) {
                    throw new DatabaseException('Impossible de créer la base de données spécifiée.');
                }

                return false;
                // @codeCoverageIgnoreEnd
            }

            if (! empty($this->db->dataCache['db_names'])) {
                $this->db->dataCache['db_names'][] = $dbName;
            }

            return true;
        } catch (Throwable $e) {
            if ($this->db->debug) {
                throw new DatabaseException('Impossible de créer la base de données spécifiée.', 0, $e);
            }

            return false; // @codeCoverageIgnore
        }
    }

    /**
     * Determine si une base de donnees existe
     *
     * @throws DatabaseException
     */
    private function databaseExists(string $dbName): bool
    {
        if ($this->checkDatabaseExistStr === null) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        return $this->db->query($this->checkDatabaseExistStr, $dbName)->first() !== null;
    }

    /**
     * Supprime la base de donnees
     *
     * @throws DatabaseException
     */
    public function dropDatabase(string $dbName): bool
    {
        if ($this->dropDatabaseStr === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        if (! $this->db->query(sprintf($this->dropDatabaseStr, $this->db->escapeIdentifier($dbName)))) {
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
     * Ajout de cle
     */
    public function addKey(array|string $key, bool $primary = false, bool $unique = false, string $keyName = ''): static
    {
        if ($primary) {
            $this->primaryKeys = ['fields' => (array) $key, 'keyName' => $keyName];
        } else {
            $this->keys[] = ['fields' => (array) $key, 'keyName' => $keyName];

            if ($unique) {
                $this->uniqueKeys[] = count($this->keys) - 1;
            }
        }

        return $this;
    }

    /**
     * Ajout de cle primaire
     */
    public function addPrimaryKey(array|string $key, string $keyName = ''): static
    {
        return $this->addKey($key, true, false, $keyName);
    }

    /**
     * Ajoute une cle unique.
     */
    public function addUniqueKey(array|string $key, string $keyName = ''): static
    {
        return $this->addKey($key, false, true, $keyName);
    }

    /**
     * Ajoute un champ.
	 * 
	 * @param array<string, array|string>|string $fields
     */
    public function addField(array|string $field): static
    {
        if (is_string($field)) {
            if ($field === 'id') {
                $this->addField([
                    'id' => [
                        'type'           => 'INT',
                        'constraint'     => 9,
                        'auto_increment' => true,
                    ],
                ]);
                $this->addKey('id', true);
            } else {
                if (! str_contains($field, ' ')) {
                    throw new InvalidArgumentException('Des informations du champ sont nécessaires pour cette opération.');
                }

                $fieldName = explode(' ', $field, 2)[0];
                $fieldName = trim($fieldName, '`\'"');

                $this->fields[$fieldName] = $field;
            }
        }

        if (is_array($field)) {
            foreach ($field as $name => $attributes) {
                if (is_string($attributes)) {
                    $this->addField($attributes);

                    continue;
                }

                if (is_array($attributes)) {
                    $this->fields = array_merge($this->fields, [$name => $attributes]);
                }
            }
        }

        return $this;
    }

    /**
     * Ajoute une cle etrangere.
     *
     * @param string|string[] $fieldName
     * @param string|string[] $tableField
     *
     * @throws DatabaseException
     */
    public function addForeignKey(array|string $fieldName = '', string $tableName = '', array|string $tableField = '', string $onUpdate = '', string $onDelete = '', string $fkName = ''): static
    {
        $fieldName  = (array) $fieldName;
        $tableField = (array) $tableField;

        $this->foreignKeys[] = [
            'field'          => $fieldName,
            'referenceTable' => $tableName,
            'referenceField' => $tableField,
            'onDelete'       => strtoupper($onDelete),
            'onUpdate'       => strtoupper($onUpdate),
            'fkName'         => $fkName,
        ];

        return $this;
    }

    /**
     * Supprime une cle.
     *
     * @throws DatabaseException
     */
    public function dropKey(string $table, string $keyName, bool $prefixKeyName = true): bool
    {
        $keyName             = $this->db->escapeIdentifiers(($prefixKeyName === true ? $this->db->prefix : '') . $keyName);
        $table               = $this->db->escapeIdentifiers($this->db->prefix . $table);
        $dropKeyAsConstraint = $this->dropKeyAsConstraint($table, $keyName);

        if ($dropKeyAsConstraint === true) {
            $sql = sprintf(
                $this->dropConstraintStr,
                $table,
                $keyName,
            );
        } else {
            $sql = sprintf(
                $this->dropIndexStr,
                $keyName,
                $table,
            );
        }

        if ($sql === '') {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        return $this->db->query($sql);
    }

    /**
     * Verifie si la cle a besoin d'etre supprimer comme contrainte.
     */
    protected function dropKeyAsConstraint(string $table, string $constraintName): bool
    {
        $sql = $this->_dropKeyAsConstraint($table, $constraintName);

        if ($sql === '') {
            return false;
        }

        return $this->db->query($sql)->resultArray() !== [];
    }

    /**
     * Construction du SQL pour verifie si la cle est une constrainte.
     */
    protected function _dropKeyAsConstraint(string $table, string $constraintName): string
    {
        return '';
    }

    /**
     * Supprime la cle primaire.
     */
    public function dropPrimaryKey(string $table, string $keyName = ''): bool
    {
        $sql = sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->db->escapeIdentifiers($this->db->prefix . $table),
            ($keyName === '') ? $this->db->escapeIdentifiers('pk_' . $this->db->prefix . $table) : $this->db->escapeIdentifiers($keyName),
        );

        return $this->db->query($sql);
    }

    /**
     * @return BaseResult|bool|false|mixed|Query
     *
     * @throws DatabaseException
     */
    public function dropForeignKey(string $table, string $foreignName)
    {
        $sql = sprintf(
            (string) $this->dropConstraintStr,
            $this->db->escapeIdentifiers($this->db->prefix . $table),
            $this->db->escapeIdentifiers($foreignName)
        );

        if ($sql === '') {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        return $this->db->query($sql);
    }

    /**
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function createTable(string $table, bool $ifNotExists = false, array $attributes = [])
    {
        if ($table === '') {
            throw new InvalidArgumentException('Un nom de table est nécessaire pour cette opération.');
        }

        $table = $this->db->prefix . $table;

        if ($this->fields === []) {
            throw new RuntimeException('Des informations sur le champ sont requises.');
        }

        // Si la table existe pas la peine d'aller plus loin
        if ($ifNotExists === true && $this->db->tableExists($table, false)) {
            $this->reset();

            return true;
        }

        $sql = $this->_createTable($table, $attributes);

        if (($result = $this->db->query($sql)) !== false) {
            if (isset($this->db->dataCache['table_names']) && ! in_array($table, $this->db->dataCache['table_names'], true)) {
                $this->db->dataCache['table_names'][] = $table;
            }

            // La plupart des bases de données ne permettent pas de créer des index à partir de l'instruction CREATE TABLE
            if (! empty($this->keys)) {
                for ($i = 0, $sqls = $this->_processIndexes($table), $c = count($sqls); $i < $c; $i++) {
                    $this->db->query($sqls[$i]);
                }
            }
        }

        $this->reset();

        return $result;
    }

    protected function _createTable(string $table, array $attributes): string
    {
        $columns = $this->_processFields(true);

        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            $columns[$i] = ($columns[$i]['_literal'] !== false) 
				? "\n\t" . $columns[$i]['_literal']
                : "\n\t" . $this->_processColumn($columns[$i]);
        }

        $columns = implode(',', $columns);

        $columns .= $this->_processPrimaryKeys($table);
        $columns .= current($this->_processForeignKeys($table));

        if ($this->createTableKeys === true) {
            $indexes = current($this->_processIndexes($table));
            if (is_string($indexes)) {
                $columns .= $indexes;
            }
        }

        return sprintf(
            $this->createTableStr . '%s',
            'CREATE TABLE',
            $this->db->escapeIdentifiers($table),
            $columns,
            $this->_createTableAttributes($attributes)
        );
    }

    protected function _createTableAttributes(array $attributes): string
    {
        $sql = '';

        foreach (array_keys($attributes) as $key) {
            if (is_string($key)) {
                $sql .= ' ' . strtoupper($key) . ' ' . $this->db->escape($attributes[$key]);
            }
        }

        return $sql;
    }

    /**
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function dropTable(string $tableName, bool $ifExists = false, bool $cascade = false)
    {
        if ($tableName === '') {
            if ($this->db->debug) {
                throw new DatabaseException('Un nom de table est nécessaire pour cette opération.');
            }

            return false;
        }

        if ($this->db->prefix !== '' && str_starts_with($tableName, $this->db->prefix)) {
            $tableName = substr($tableName, strlen($this->db->prefix));
        }

        if (($query = $this->_dropTable($this->db->prefix . $tableName, $ifExists, $cascade)) === true) {
            return true;
        }

        $this->db->disableForeignKeyChecks();

        $query = $this->db->query($query);

        $this->db->enableForeignKeyChecks();

        if ($query && ! empty($this->db->dataCache['table_names'])) {
            $key = array_search(
                strtolower($this->db->prefix . $tableName),
                array_map('strtolower', $this->db->dataCache['table_names']),
                true
            );

            if ($key !== false) {
                unset($this->db->dataCache['table_names'][$key]);
            }
        }

        return $query;
    }

    /**
     * Genere la chaine DROP TABLE specifiquement a la plateforme utilisee
     *
     * @return bool|string
     */
    protected function _dropTable(string $table, bool $ifExists, bool $cascade)
    {
        $sql = 'DROP TABLE';

        if ($ifExists) {
            if ($this->dropTableIfStr === false) {
                if (! $this->db->tableExists($table)) {
                    return true;
                }
            } else {
                $sql = sprintf($this->dropTableIfStr, $this->db->escapeIdentifiers($table));
            }
        }

        return $sql . ' ' . $this->db->escapeIdentifiers($table);
    }

    /**
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function renameTable(string $tableName, string $newTableName)
    {
        if ($tableName === '' || $newTableName === '') {
            throw new InvalidArgumentException('Un nom de table est nécessaire pour cette opération.');
        }

        if ($this->renameTableStr === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        $result = $this->db->query(sprintf(
            $this->renameTableStr,
            $this->db->escapeIdentifiers($this->db->prefix . $tableName),
            $this->db->escapeIdentifiers($this->db->prefix . $newTableName)
        ));

        if ($result && ! empty($this->db->dataCache['table_names'])) {
            $key = array_search(
                strtolower($this->db->prefix . $tableName),
                array_map('strtolower', $this->db->dataCache['table_names']),
                true
            );

            if ($key !== false) {
                $this->db->dataCache['table_names'][$key] = $this->db->prefix . $newTableName;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array|string>|string $field
	 * 
     * @throws DatabaseException
     */
    public function addColumn(string $table, array|string $field): bool
    {
		// Solution de contournement pour les définitions de colonnes littérales
        if (! is_array($field)) {
            $field = [$field];
        }

        foreach (array_keys($field) as $name) {
            $this->addField([$name => $field[$name]]);
        }

        $sqls = $this->_alterTable('ADD', $this->db->prefix . $table, $this->_processFields());
        $this->reset();

        if ($sqls === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        foreach ($sqls as $sql) {
            if ($this->db->query($sql) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string>|string $columnName Noms des champs à supprimer
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function dropColumn(string $table, array|string $columnName)
    {
        $sql = $this->_alterTable('DROP', $this->db->prefix . $table, $columnName);
        
		if ($sql === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        return $this->db->query($sql);
    }

    /**
     * @throws DatabaseException
     */
    public function modifyColumn(string $table, array|string $field): bool
    {
		// Solution de contournement pour les définitions de colonnes littérales
        if (! is_array($field)) {
            $field = [$field];
        }

        foreach (array_keys($field) as $name) {
            $this->addField([$name => $field[$name]]);
        }

        if ($this->fields === []) {
            throw new RuntimeException('Les informations du champ sont requises');
        }

        $sqls = $this->_alterTable('CHANGE', $this->db->prefix . $table, $this->_processFields());
        $this->reset();

        if ($sqls === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Cette fonction n\'est pas disponible pour la base de données que vous utilisez.');
            }

            return false;
        }

        if (is_array($sqls)) {
            foreach ($sqls as $sql) {
                if ($this->db->query($sql) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Renomme une colonne dans la table spécifiée.
     *
     * Cette fonction renomme une colonne de la table donnée de son nom actuel à un nouveau nom.
     * Elle récupère les propriétés de la colonne existante et les utilise pour modifier la colonne avec le nouveau nom.
     *
     * @throws RuntimeException Si la colonne spécifiée n'existe pas dans la table.
     */
    public function renameColumn(string $table, string $from, string $to): bool
    {
        $field = array_filter($this->db->getFieldData($table), fn($field) => $field->name === $from);
        $field = array_shift($field);

        if (null === $field) {
            throw new RuntimeException(sprintf("La colonne %s n'existe pas dans la table %s", $from, $table));
        }

        return $this->modifyColumn($table, [$from => [
            'name'       => $to,
            'type'       => strtoupper($field->type),
            'constraint' => $field->max_length,
            'null'       => $field->nullable,
        ]]);
    }

    /**
     * @param 'ADD'|'CHANGE'|'DROP' $alterType
     * @param array|string          $processedFields Définitions de colonnes traitées ou noms de colonnes à DROP
     *
     * @return ($alterType is 'DROP' ? string : false|list<string>|null)
     */
    protected function _alterTable(string $alterType, string $table, array|string $processedFields)
    {
        $sql = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table) . ' ';

        // DROP a maintenant tout ce qu'il lui faut.
        if ($alterType === 'DROP') {
            if (is_string($processedFields)) {
                $processedFields = explode(',', $processedFields);
            }

            $processedFields = array_map(fn ($field) => 'DROP COLUMN ' . $this->db->escapeIdentifiers(trim($field)), $processedFields);

            return $sql . implode(', ', $processedFields);
        }

        $sql .= ($alterType === 'ADD') ? 'ADD ' : $alterType . ' COLUMN ';

        $sqls = [];

        foreach ($processedFields as $field) {
            $sqls[] = $sql . ($field['_literal'] !== false
                ? $field['_literal']
                : $this->_processColumn($field));
        }

        return $sqls;
    }

    /**
     * Process fields
	 * 
	 * @return array Retourne le tableau $processedFields à partir des données de $this->fields.
     */
    protected function _processFields(bool $createTable = false): array
    {
        $processedFields = [];

        foreach ($this->fields as $key => $attributes) {
            if (! is_array($attributes)) {
                $processedFields[] = ['_literal' => $attributes];

                continue;
            }

            $attributes = array_change_key_case($attributes, CASE_UPPER);

            if ($createTable === true && empty($attributes['TYPE'])) {
                continue;
            }

            if (isset($attributes['TYPE'])) {
                $this->_attributeType($attributes);
            }

            $field = [
                'name'           => $key,
                'new_name'       => $attributes['NAME'] ?? null,
                'type'           => $attributes['TYPE'] ?? null,
                'length'         => '',
                'unsigned'       => '',
                'null'           => '',
                'unique'         => '',
                'default'        => '',
                'auto_increment' => '',
                '_literal'       => false,
            ];

            if (isset($attributes['TYPE'])) {
                $this->_attributeUnsigned($attributes, $field);
            }

            if ($createTable === false) {
                if (isset($attributes['AFTER'])) {
                    $field['after'] = $attributes['AFTER'];
                } elseif (isset($attributes['FIRST'])) {
                    $field['first'] = (bool) $attributes['FIRST'];
                }
            }

            $this->_attributeDefault($attributes, $field);

            if (isset($attributes['NULL'])) {
                $nullString = ' ' . $this->null;

                if ($attributes['NULL'] === true) {
                    $field['null'] = empty($this->null) ? '' : $nullString;
                } elseif ($attributes['NULL'] === $nullString) {
                    $field['null'] = $nullString;
                } elseif ($attributes['NULL'] === '') {
                    $field['null'] = '';
                } else {
                    $field['null'] = ' NOT ' . $this->null;
                }
            } elseif ($createTable === true) {
                $field['null'] = ' NOT ' . $this->null;
            }

            $this->_attributeAutoIncrement($attributes, $field);
            $this->_attributeUnique($attributes, $field);

            if (isset($attributes['COMMENT'])) {
                $field['comment'] = $this->db->escape($attributes['COMMENT']);
            }

            if (isset($attributes['TYPE']) && ! empty($attributes['CONSTRAINT'])) {
                if (is_array($attributes['CONSTRAINT'])) {
                    $attributes['CONSTRAINT'] = $this->db->escape($attributes['CONSTRAINT']);
                    $attributes['CONSTRAINT'] = implode(',', $attributes['CONSTRAINT']);
                }

                $field['length'] = '(' . $attributes['CONSTRAINT'] . ')';
            }

            $processedFields[] = $field;
        }

        return $processedFields;
    }

    /**
     * Convertit le tableau $field en chaîne de définition de champ.
     */
    protected function _processColumn(array $field): string
    {
        return $this->db->escapeIdentifiers($field['name'])
            . ' ' . $field['type'] . $field['length']
            . $field['unsigned']
            . $field['default']
            . $field['null']
            . $field['auto_increment']
            . $field['unique'];
    }

    /**
     * Performs a data type mapping between different databases.
     */
    protected function _attributeType(array &$attributes)
    {
        // Usually overridden by drivers
    }

    /**
     * En fonction de la valeur de la propriété non signée :
     *
     * - TRUE mettra toujours $field['unsigned'] à 'UNSIGNED'
     * - FALSE mettra toujours $field[“unsigned'] à ''
     * - array(TYPE) mettra $field['unsigned'] à 'UNSIGNED',
     * 		si $attributes['TYPE'] est trouvé dans le tableau
     * - array(TYPE => UTYPE) changera $field['type'],
     * 		de TYPE à UTYPE en cas de correspondance
     */
    protected function _attributeUnsigned(array &$attributes, array &$field)
    {
        if (empty($attributes['UNSIGNED']) || $attributes['UNSIGNED'] !== true) {
            return;
        }

        // Réinitialiser l'attribut afin d'éviter des problèmes si nous effectuons une conversion de type
        $attributes['UNSIGNED'] = false;

        if (is_array($this->unsigned)) {
            foreach (array_keys($this->unsigned) as $key) {
                if (is_int($key) && strcasecmp($attributes['TYPE'], $this->unsigned[$key]) === 0) {
                    $field['unsigned'] = ' UNSIGNED';

                    return;
                }

                if (is_string($key) && strcasecmp($attributes['TYPE'], $key) === 0) {
                    $field['type'] = $key;

                    return;
                }
            }

            return;
        }

        $field['unsigned'] = ($this->unsigned === true) ? ' UNSIGNED' : '';
    }

    protected function _attributeDefault(array &$attributes, array &$field)
    {
        if ($this->default === false) {
            return;
        }

        if (array_key_exists('DEFAULT', $attributes)) {
            if ($attributes['DEFAULT'] === null) {
                $field['default'] = empty($this->null) ? '' : $this->default . $this->null;

                // Remplacer l'attribut NULL si c'est notre valeur par défaut
                $attributes['NULL'] = true;
                $field['null']      = empty($this->null) ? '' : ' ' . $this->null;
            } elseif ($attributes['DEFAULT'] instanceof RawSql) {
                $field['default'] = $this->default . $attributes['DEFAULT'];
            } else {
                $field['default'] = $this->default . $this->db->escape($attributes['DEFAULT']);
            }
        }
    }

    protected function _attributeUnique(array &$attributes, array &$field)
    {
        if (! empty($attributes['UNIQUE']) && $attributes['UNIQUE'] === true) {
            $field['unique'] = ' UNIQUE';
        }
    }

    protected function _attributeAutoIncrement(array &$attributes, array &$field)
    {
        if (! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true
            && str_contains(strtolower($field['type']), 'int')
        ) {
            $field['auto_increment'] = ' AUTO_INCREMENT';
        }
    }

    /**
     * Genere le SQL pour ajouter la cle primaire.
     *
     * @param bool $asQuery Quand true retourne le SQL complet sinon le SQL partiel SQL utiliser avec CREATE TABLE
     */
    protected function _processPrimaryKeys(string $table, bool $asQuery = false): string
    {
        $sql = '';

        if (isset($this->primaryKeys['fields'])) {
            for ($i = 0, $c = count($this->primaryKeys['fields']); $i < $c; $i++) {
                if (! isset($this->fields[$this->primaryKeys['fields'][$i]])) {
                    unset($this->primaryKeys['fields'][$i]);
                }
            }
        }

        if (isset($this->primaryKeys['fields']) && $this->primaryKeys['fields'] !== []) {
            if ($asQuery === true) {
                $sql .= 'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefix . $table) . ' ADD ';
            } else {
                $sql .= ",\n\t";
            }
            $sql .= 'CONSTRAINT ' . $this->db->escapeIdentifiers(($this->primaryKeys['keyName'] === '' ?
                'pk_' . $table :
                $this->primaryKeys['keyName']))
                    . ' PRIMARY KEY(' . implode(', ', $this->db->escapeIdentifiers($this->primaryKeys['fields'])) . ')';
        }

        return $sql;
    }

    /**
     * Executes le Sql pour ajouter les indexes sans createTable.
     */
    public function processIndexes(string $table): bool
    {
        $sqls = [];
        $fk   = $this->foreignKeys;

        if ([] === $this->fields) {
            $fieldData =  $this->db->getFieldData($this->db->prefix . $table);

            $this->fields = array_combine(
                array_map(static fn ($columnName) => $columnName->name, $fieldData),
                array_fill(0, count($fieldData), []),
            );
        }

        $fields = $this->fields;

        if ([] !== $this->keys) {
            $sqls = $this->_processIndexes($this->db->prefix . $table, true);
        }

        if ([] !== $this->primaryKeys) {
            $sqls[] = $this->_processPrimaryKeys($table, true);
        }

        $this->foreignKeys = $fk;
        $this->fields      = $fields;

        if ([] !== $this->foreignKeys) {
            $sqls = array_merge($sqls, $this->_processForeignKeys($table, true));
        }

        foreach ($sqls as $sql) {
            if ($this->db->query($sql) === false) {
                return false;
            }
        }

        $this->reset();

        return true;
    }

    /**
     * Genere le SQL pour ajouter les indexes.
     *
     * @param bool $asQuery Quand true retourne le SQL complet sinon le SQL partiel SQL utiliser avec CREATE TABLE
     */
    protected function _processIndexes(string $table, bool $asQuery = false): array
    {
        $sqls = [];

        for ($i = 0, $c = count($this->keys); $i < $c; $i++) {
            for ($i2 = 0, $c2 = count($this->keys[$i]['fields']); $i2 < $c2; $i2++) {
                if (! isset($this->fields[$this->keys[$i]['fields'][$i2]])) {
                    unset($this->keys[$i]['fields'][$i2]);
                }
            }

            if (count($this->keys[$i]['fields']) <= 0) {
                continue;
            }

            $keyName = $this->db->escapeIdentifiers(($this->keys[$i]['keyName'] === '') ?
                $table . '_' . implode('_', $this->keys[$i]['fields']) :
                $this->keys[$i]['keyName']);

            if (in_array($i, $this->uniqueKeys, true)) {
                if ($this->db->driver === 'SQLite3') {
                    $sqls[] = 'CREATE UNIQUE INDEX ' . $keyName
                        . ' ON ' . $this->db->escapeIdentifiers($table)
                        . ' (' . implode(', ', $this->db->escapeIdentifiers($this->keys[$i]['fields'])) . ')';
                } else {
                    $sqls[] = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table)
                        . ' ADD CONSTRAINT ' . $keyName
                        . ' UNIQUE (' . implode(', ', $this->db->escapeIdentifiers($this->keys[$i]['fields'])) . ')';
                }

                continue;
            }

            $sqls[] = 'CREATE INDEX ' . $keyName
                . ' ON ' . $this->db->escapeIdentifiers($table)
                . ' (' . implode(', ', $this->db->escapeIdentifiers($this->keys[$i]['fields'])) . ')';
        }

        return $sqls;
    }

    /**
     * Genere le SQL pour ajouter les cles etrangeres.
     *
     * @param bool $asQuery Quand true retourne le SQL complet sinon le SQL partiel SQL utiliser avec CREATE TABLE
     */
    protected function _processForeignKeys(string $table, bool $asQuery = false): array
    {
        $errorNames = [];

        foreach ($this->foreignKeys as $fkeyInfo) {
            foreach ($fkeyInfo['field'] as $fieldName) {
                if (! isset($this->fields[$fieldName])) {
                    $errorNames[] = $fieldName;
                }
            }
        }

        if ($errorNames !== []) {
            $errorNames = [implode(', ', $errorNames)];

            throw new DatabaseException('Field "' . $errorNames . '" not found.');
        }

        $sqls = [''];

        foreach ($this->foreignKeys as $index => $fkey) {
            if ($asQuery === false) {
                $index = 0;
            } else {
                $sqls[$index] = '';
            }

            $nameIndex = $fkey['fkName'] !== '' ?
            $fkey['fkName'] :
            $table . '_' . implode('_', $fkey['field']) . ($this->db->driver === 'OCI8' ? '_fk' : '_foreign');

            $nameIndexFilled      = $this->db->escapeIdentifiers($nameIndex);
            $foreignKeyFilled     = implode(', ', $this->db->escapeIdentifiers($fkey['field']));
            $referenceTableFilled = $this->db->escapeIdentifiers($this->db->prefix . $fkey['referenceTable']);
            $referenceFieldFilled = implode(', ', $this->db->escapeIdentifiers($fkey['referenceField']));

            if ($asQuery === true) {
                $sqls[$index] .= 'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefix . $table) . ' ADD ';
            } else {
                $sqls[$index] .= ",\n\t";
            }

            $formatSql = 'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s)';
            $sqls[$index] .= sprintf($formatSql, $nameIndexFilled, $foreignKeyFilled, $referenceTableFilled, $referenceFieldFilled);

            if ($fkey['onDelete'] !== false && in_array($fkey['onDelete'], $this->fkAllowActions, true)) {
                $sqls[$index] .= ' ON DELETE ' . $fkey['onDelete'];
            }

            if ($this->db->driver !== 'OCI8' && $fkey['onUpdate'] !== false && in_array($fkey['onUpdate'], $this->fkAllowActions, true)) {
                $sqls[$index] .= ' ON UPDATE ' . $fkey['onUpdate'];
            }
        }

        return $sqls;
    }

    /**
     * Reinitialise les variables
     */
    public function reset()
    {
        $this->fields = $this->keys = $this->uniqueKeys = $this->primaryKeys = $this->foreignKeys = [];
    }
}
