<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Creator\SQLite;

use BlitzPHP\Database\Connection\SQLite as Connection;
use BlitzPHP\Database\Creator\SQLite as Creator;
use BlitzPHP\Database\Exceptions\DataException;
use stdClass;

/**
 * Fournit les fonctionnalités manquantes pour la modification des tables qui sont courantes 
 * dans les autres bases de données supportées, mais qui sont absentes de SQLite.
 * Ces fonctionnalités sont nécessaires pour prendre en charge les migrations lors des tests
 * lorsqu'une autre base de données est utilisée comme moteur principal, 
 * mais que les bases de données SQLite en mémoire sont utilisées pour une exécution plus rapide des tests.
 */
class Table
{
    /**
     * Tous les champs que ce tableau représente.
     *
     * @var array<string, array<string, bool|int|string|null>> [name => attributes]
     */
    protected array $fields = [];

    /**
     * Toutes les clés uniques/primaires de la table.
     */
    protected array $keys = [];

    /**
     * Toutes les clés étrangères de la table.
     */
    protected array $foreignKeys = [];

    /**
     * Le nom de la table avec laquelle nous travaillons.
     */
    protected string $tableName = '';

    /**
     * Le nom de la table, avec le préfixe de la base de données.
     */
    protected string $prefixedTableName = '';

    /**
     * @param Connection $db Connexion à la base de données.
     * @param Creator $creator La main de notre créateur.
     */
    public function __construct(protected Connection $db, protected Creator $creator)
    {
    }

    /**
     * Lit une table de base de données existante et collecte toutes les informations nécessaires pour recréer cette table.
     */
    public function fromTable(string $table): self
    {
        $this->prefixedTableName = $table;

        $prefix = $this->db->prefix;

        if (! empty($prefix) && str_starts_with($table, $prefix)) {
            $table = substr($table, strlen($prefix));
        }

        if (! $this->db->tableExists($this->prefixedTableName)) {
            throw DataException::tableNotFound($this->prefixedTableName);
        }

        $this->tableName = $table;

        $this->fields = $this->formatFields($this->db->getFieldData($table));

        $this->keys = array_merge($this->keys, $this->formatKeys($this->db->getIndexData($table)));

        // si l'index de la clé primaire existe deux fois, supprimer le nom de l'index pseudo "primaire".
        $primaryIndexes = array_filter($this->keys, static fn ($index): bool => $index['type'] === 'primary');

        if ($primaryIndexes !== [] && count($primaryIndexes) > 1 && array_key_exists('primary', $this->keys)) {
            unset($this->keys['primary']);
        }

        $this->foreignKeys = $this->db->getForeignKeyData($table);

        return $this;
    }

    /**
     * Appelé après `fromTable` et toute autre action, comme `dropColumn`, etc, pour finaliser l'action.
     * Il crée une table temporaire, crée la nouvelle table avec les modifications, et copie les données dans la nouvelle table.
     * Il réinitialise la connexion dataCache pour s'assurer que les changements sont collectés.
     */
    public function run(): bool
    {
        $this->db->query('PRAGMA foreign_keys = OFF');

        $this->db->transStart();

        $this->creator->renameTable($this->tableName, "temp_{$this->tableName}");

        $this->creator->reset();

        $this->createTable();

        $this->copyData();

        $this->creator->dropTable("temp_{$this->tableName}");

        $success = $this->db->transComplete();

        $this->db->query('PRAGMA foreign_keys = ON');

        $this->db->resetDataCache();

        return $success;
    }

    /**
     * Supprime des colonnes du tableau.
     *
     * @param list<string>|string $columns Noms de colonnes à supprimer.
     */
    public function dropColumn(array|string $columns): self
    {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }

        foreach ($columns as $column) {
            $column = trim($column);
            if (isset($this->fields[$column])) {
                unset($this->fields[$column]);
            }
        }

        return $this;
    }

    /**
     * Modifie un champ, notamment en changeant le type de données, en le renommant, etc.
     *
     * @param list<array<string, bool|int|string|null>> $fieldsToModify
     */
    public function modifyColumn(array $fieldsToModify): self
    {
        foreach ($fieldsToModify as $field) {
            $oldName = $field['name'];
            unset($field['name']);

            $this->fields[$oldName] = $field;
        }

        return $this;
    }

    /**
     * Supprime la clé primaire
     */
    public function dropPrimaryKey(): self
    {
        $primaryIndexes = array_filter($this->keys, static fn ($index): bool => strtolower($index['type']) === 'primary');

        foreach (array_keys($primaryIndexes) as $key) {
            unset($this->keys[$key]);
        }

        return $this;
    }

    /**
     * Supprime une clé étrangère de cette table afin qu'elle ne soit pas recréée à l'avenir.
     */
    public function dropForeignKey(string $foreignName): self
    {
        if (empty($this->foreignKeys)) {
            return $this;
        }

        if (isset($this->foreignKeys[$foreignName])) {
            unset($this->foreignKeys[$foreignName]);
        }

        return $this;
    }

    /**
     * Ajoute une clé primaire
     */
    public function addPrimaryKey(array $fields): self
    {
        $primaryIndexes = array_filter($this->keys, static fn ($index): bool => strtolower($index['type']) === 'primary');

        // si la clé primaire existe déjà, nous ne pouvons pas en ajouter une autre
        if ($primaryIndexes !== []) {
            return $this;
        }

        // ajouter un tableau aux clés des champs
        $pk = [
            'fields' => $fields['fields'],
            'type'   => 'primary',
        ];

        $this->keys['primary'] = $pk;

        return $this;
    }

    /**
     * Ajouter une clé étrangère
     */
    public function addForeignKey(array $foreignKeys): self
    {
        $fk = [];

        // convertir en objet
        foreach ($foreignKeys as $row) {
            $obj                      = new stdClass();
            $obj->column_name         = $row['field'];
            $obj->foreign_table_name  = $row['referenceTable'];
            $obj->foreign_column_name = $row['referenceField'];
            $obj->on_delete           = $row['onDelete'];
            $obj->on_update           = $row['onUpdate'];

            $fk[] = $obj;
        }

        $this->foreignKeys = array_merge($this->foreignKeys, $fk);

        return $this;
    }

    /**
     * Crée la nouvelle table sur la base de nos champs actuels.
     *
     * @return mixed
     */
    protected function createTable()
    {
        $this->dropIndexes();
        $this->db->resetDataCache();

        // Traiter les colonnes modifiées.
        $fields = [];

        foreach ($this->fields as $name => $field) {
            if (isset($field['new_name'])) {
                $fields[$field['new_name']] = $field;

                continue;
            }

            $fields[$name] = $field;
        }

        $this->creator->addField($fields);

        $fieldNames = array_keys($fields);

        $this->keys = array_filter(
            $this->keys,
            static fn ($index): bool => count(array_intersect($index['fields'], $fieldNames)) === count($index['fields']),
        );

        // clés Unique/Index
        if (is_array($this->keys)) {
            foreach ($this->keys as $keyName => $key) {
                switch ($key['type']) {
                    case 'primary':
                        $this->creator->addPrimaryKey($key['fields']);
                        break;

                    case 'unique':
                        $this->creator->addUniqueKey($key['fields'], $keyName);
                        break;

                    case 'index':
                        $this->creator->addKey($key['fields'], false, false, $keyName);
                        break;
                }
            }
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $this->creator->addForeignKey(
                $foreignKey->column_name,
                trim($foreignKey->foreign_table_name, $this->db->DBPrefix),
                $foreignKey->foreign_column_name
            );
        }

        return $this->creator->createTable($this->tableName);
    }

    /**
     * Copie les données de notre ancienne table vers la nouvelle,
     * en prenant soin de mapper correctement les données en fonction des colonnes qui ont été renommées.
     */
    protected function copyData(): void
    {
        $exFields  = [];
        $newFields = [];

        foreach ($this->fields as $name => $details) {
            $newFields[] = $details['new_name'] ?? $name;
            $exFields[]  = $name;
        }

        $exFields = implode(
            ', ',
            array_map(fn ($item) => $this->db->protectIdentifiers($item), $exFields)
        );
        $newFields = implode(
            ', ',
            array_map(fn ($item) => $this->db->protectIdentifiers($item), $newFields)
        );

        $this->db->query(
            "INSERT INTO {$this->prefixedTableName}({$newFields}) SELECT {$exFields} FROM {$this->db->DBPrefix}temp_{$this->tableName}"
        );
    }

    /**
     * Convertit les champs récupérés dans la base de données au format requis pour créer des champs avec Creator.
     *
     * @param array|bool $fields
     *
     * @return ($fields is array ? array : mixed)
     */
    protected function formatFields($fields)
    {
        if (! is_array($fields)) {
            return $fields;
        }

        $return = [];

        foreach ($fields as $field) {
            $return[$field->name] = [
                'type'    => $field->type,
                'default' => $field->default,
                'null'    => $field->nullable,
            ];

            if ($field->default === null) {
                // `null` signifie que la valeur par défaut n'est pas définie.
                unset($return[$field->name]['default']);
            } elseif ($field->default === 'NULL') {
                // `NULL` signifie que la valeur par défaut est NULL..
                $return[$field->name]['default'] = null;
            } else {
                $default = trim($field->default, "'");

                if ($this->isIntegerType($field->type)) {
                    $default = (int) $default;
                } elseif ($this->isNumericType($field->type)) {
                    $default = (float) $default;
                }

                $return[$field->name]['default'] = $default;
            }

            if ($field->primary_key) {
                $this->keys['primary'] = [
                    'fields' => [$field->name],
                    'type'   => 'primary',
                ];
            }
        }

        return $return;
    }

    /**
     * Est-ce un type INTEGER ?
     *
     * @param string $type Type de données SQLite (insensible à la casse)
     *
     * @see https://www.sqlite.org/datatype3.html
     */
    private function isIntegerType(string $type): bool
    {
        return str_contains(strtoupper($type), 'INT');
    }

    /**
     * Est-ce un type NUMERIC ?
     *
     * @param string $type Type de données SQLite (insensible à la casse)
     *
     * @see https://www.sqlite.org/datatype3.html
     */
    private function isNumericType(string $type): bool
    {
        return in_array(strtoupper($type), ['NUMERIC', 'DECIMAL'], true);
    }

    /**
     * Convertit les clés récupérées dans la base de données au format nécessaire pour la création ultérieure.
     *
     * @param array<string, stdClass> $keys
     *
     * @return array<string, array{fields: string, type: string}>
     */
    protected function formatKeys($keys)
    {
        $return = [];

        foreach ($keys as $name => $key) {
            $return[strtolower($name)] = [
                'fields' => $key->fields,
                'type'   => strtolower($key->type),
            ];
        }

        return $return;
    }

    /**
     * Tente de supprimer tous les index et contraintes de la base de données pour cette table.
     *
     * @return void
     */
    protected function dropIndexes()
    {
        if (! is_array($this->keys) || $this->keys === []) {
            return;
        }

        foreach (array_keys($this->keys) as $name) {
            if ($name === 'primary') {
                continue;
            }

            $this->db->query("DROP INDEX IF EXISTS '{$name}'");
        }
    }
}
