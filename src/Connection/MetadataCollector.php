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

/**
 * Collecteur de métadonnées pour les bases de données
 */
class MetadataCollector
{
    /**
     * Cache des métadonnées
     * 
     * @var array{table: array, columns: array[], indexes: array, foreign_keys: array}
     */
    protected array $cache = [
        'tables'       => [],
        'columns'      => [],
        'indexes'      => [],
        'foreign_keys' => [],
    ];

    /**
     * Constructeur
     * 
     * @param BaseConnection $db Instance de connexion
     */
    public function __construct(protected BaseConnection $db)
    {
    }

    /**
     * Vide le cache
     */
    public function clearCache(): self
    {
        $this->cache = [
            'tables'       => [],
            'columns'      => [],
            'indexes'      => [],
            'foreign_keys' => [],
        ];
        
        return $this;
    }

    /**
     * Retourne la liste des tables
     */
    public function listTables(bool $constrainByPrefix = false): array
    {
        if ($this->cache['tables'] !== []) {
            return $this->filterTables($this->cache['tables'], $constrainByPrefix);
        }

        $result = $this->db->query($this->db->_listTables($constrainByPrefix));
        
        $tables = [];
        foreach ($result->resultArray() as $row) {
            $tables[] = current($row);
        }
        
        $this->cache['tables'] = $tables;
        
        return $this->filterTables($tables, $constrainByPrefix);
    }

    /**
     * Vérifie si une table existe
     */
    public function tableExists(string $tableName, bool $cached = true): bool
    {
        $tables = $this->listTables(false);
        
        $tableName = str_replace($this->db->getPrefix(), '', $tableName);
        
        return in_array($tableName, $tables, true) 
            || in_array($this->db->getPrefix() . $tableName, $tables, true);
    }

    /**
     * Retourne les noms des champs d'une table
     */
    public function getColumnNames(string $table): array
    {
        $data = $this->getColumnData($table);
        
        return array_column($data, 'name');
    }

    /**
     * Vérifie si un champ existe
     */
    public function columnExists(string $column, string $table): bool
    {
        $columns = $this->getColumnNames($table);
        
        return in_array($column, $columns, true);
    }

    /**
     * Retourne les informations détaillées des champs
     */
    public function getColumnData(string $table): array
    {
        if (isset($this->cache['columns'][$table])) {
            return $this->cache['columns'][$table];
        }

        $columns = $this->db->_listColumns($table);
        
        $this->cache['columns'][$table] = $columns;
        
        return $columns;
    }

    /**
     * Retourne les informations des index
     */
    public function getIndexData(string $table): array
    {
        if (isset($this->cache['indexes'][$table])) {
            return $this->cache['indexes'][$table];
        }

        $indexes = $this->db->_listIndexes($table);
        
        $this->cache['indexes'][$table] = $indexes;
        
        return $indexes;
    }

    /**
     * Retourne les informations des clés étrangères
     */
    public function getForeignKeyData(string $table): array
    {
        if (isset($this->cache['foreign_keys'][$table])) {
            return $this->cache['foreign_keys'][$table];
        }

        $keys = $this->db->_listForeignKeys($table);
        
        $this->cache['foreign_keys'][$table] = $keys;
        
        return $keys;
    }

    /**
     * Filtre les tables par préfixe si nécessaire
     */
    protected function filterTables(array $tables, bool $constrainByPrefix): array
    {
        if (!$constrainByPrefix || $this->db->getPrefix() === '') {
            return $tables;
        }
        
        return array_filter($tables, fn($table) => str_starts_with($table, $this->db->getPrefix()));
    }
}