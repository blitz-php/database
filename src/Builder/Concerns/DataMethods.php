<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder\Concerns;

use BlitzPHP\Contracts\Database\BuilderInterface;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Database\Query\Result;
use BlitzPHP\Database\Utils;
use Closure;
use Generator;
use PDO;

/**
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait DataMethods
{
    /**
     * Cache des informations sur les colonnes sélectionnées
     */
    protected array $selectedColumnsCache = [];

    /*
    |--------------------------------------------------------------------------
    | AGGREGATE METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère la valeur minimale d'un champ
     */
    public function min(string $column)
    {
        return $this->aggregate('min', $column);
    }

    /**
     * Récupère la valeur maximale d'un champ
     */
    public function max(string $column)
    {
        return $this->aggregate('max', $column);
    }

    /**
     * Récupère la somme des valeurs d'un champ
     */
    public function sum(string $column)
    {
        return $this->aggregate('sum', $column);
    }

    /**
     * Récupère la moyenne des valeurs d'un champ
     */
    public function avg(string $column)
    {
        return $this->aggregate('avg', $column);
    }

    /**
     * Récupère le nombre d'enregistrements
     */
    public function count(string $column = '*')
    {
        $builder = $this->clone();
        $column  = $this->buildColumnName($column);

        if ($builder->distinct || $builder->hasGroup()) {
            $builder = $this->fromSubquery($builder, 'count_table')
                ->selectRaw('COUNT(' . $column . ') AS count_value');
        } else {
            $builder = $builder->selectRaw('COUNT(' . $column . ') AS count_value');
        }

        return $this->testMode ? $builder->toSql() : (int) ($builder->value('count_value') ?? 0);
    }

    /**
     * Récupère le nombre de résultats distincts
     */
    public function countDistinct(string $column)
    {
        return $this->clone()->distinct()->count($column);
    }

    /**
     * Génère une chaîne de requête spécifique à la plateforme qui compte tous les enregistrements renvoyés par une requête Query Builder.
     *
     * @return int|string int en mode reel et string (la chaîne SQL) en mode test
     */
    public function countAllResults()
    {
        $clone = $this->clone();

        $clone->limit = null;

        return $clone->withoutOrder()->count();
    }

    /**
     * @return float|string
     */
    public function aggregate(string $type, string $column)
    {
        $alias  = $type . '_value';
        $column = $this->buildColumnName($column);

        $result = $this->clone()->selectRaw(
            sprintf('%s(%s) AS %s', 
                strtoupper($type), 
                $column, 
                $this->db->escapeIdentifiers($alias)
            )
        );

        return $this->testMode ? $result->sql() : (float) ($result->value($alias) ?? 0);
    }

    /*
    |--------------------------------------------------------------------------
    | INSERT METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Insère en utilisant le résultat d'une sous-requête
     *
     * @return int|string
     */
    public function insertUsing(array $columns, BuilderInterface|Closure $query)
    {
        $this->crud = 'insert';

        if ($query instanceof Closure) {
            $builder = $this->newQuery();
            $query($builder);
            $query = $builder;
        }

        $this->columns = $columns;
        $this->values  = ['query' => $query];

        if ($this->testMode) {
            return $this->compiler->compileInsertUsing($this);
        }

        $result = $this->execute();

        return $result instanceof Result ? $result->affectedRows() : 0;
    }

    /**
     * Insère et récupère l'ID généré
     *
     * @return int|static|string|null
     */
    public function insertGetId(array $values, ?string $sequence = null)
    {
        if (is_bool($inserted = $this->insert($values))) {
            return $inserted === true ? $this->db->lastId($this->getTable()) : null;
        }

        return $inserted;
    }

    /**
     * Insère et récupère l'enregistrement inséré
     *
     * @return object|static|string|null
     */
    public function insertAndGet(array $values)
    {
        if (is_int($id = $this->insertGetId($values))) {
            return $this->clone()->where($this->getKeyName(), $id)->first();
        }

        return $id;
    }

    /**
     * Récupère le nom de la clé primaire (à surcharger si différent)
     */
    protected function getKeyName(): string
    {
        return 'id';
    }

    /*
    |--------------------------------------------------------------------------
    | RAW EXPRESSIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Crée une expression SQL brute
     */
    public static function raw(string $value): Expression
    {
        return new Expression($value);
    }

    /**
     * Ajoute une expression brute dans la clause SELECT
     */
    public function selectRaw(Expression|string $expression, array $bindings = [])
    {
        if (is_string($expression)) {
            $expression = new Expression($expression);
        }

        $this->columns[] = $expression;
        $this->bindings->addMany($bindings);

        return $this;
    }

    /**
     * Ajoute une expression brute dans la clause WHERE
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type'    => 'raw',
            'sql'     => $sql,
            'boolean' => $boolean,
        ];

        $this->bindings->addMany($bindings);

        return $this;
    }

    /**
     * Ajoute une expression brute dans la clause WHERE avec OR
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'or');
    }

    /**
     * Ajoute une clause HAVING avec une expression brute
     */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $this->havings[] = [
            'type'    => 'raw',
            'sql'     => $sql,
            'boolean' => $boolean,
        ];

        $this->bindings->addMany($bindings);

        return $this;
    }

    /**
     * Ajoute une clause HAVING avec une expression brute et OR
     */
    public function orHavingRaw(string $sql, array $bindings = []): static
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Ajoute une expression brute dans la clause ORDER BY
     */
    public function orderByRaw(string $expression, array $bindings = []): self
    {
        $this->orders[] = [
            'column'    => new Expression($expression),
            'direction' => '',
            'raw'       => true,
        ];

        $this->bindings->addMany($bindings);

        return $this->asCrud('select');
    }

    /**
     * Ajoute une expression brute dans la clause GROUP BY
     */
    public function groupByRaw(string $expression, array $bindings = []): self
    {
        $this->groups[] = new Expression($expression);
        $this->bindings->addMany($bindings);

        return $this->asCrud('select');
    }

    /**
     * Ajoute une expression brute dans la clause JOIN
     */
    public function joinRaw(string $table, string $on, array $bindings = [], string $type = 'INNER'): self
    {
        $this->joins[] = $type . ' JOIN ' . $table . ' ON ' . $on;
        $this->bindings->addMany($bindings);

        return $this->asCrud('select');
    }

    /**
     * Vérifie si une valeur est une expression brute
     */
    protected function isRawExpression(mixed $value): bool
    {
        return $value instanceof Expression;
    }
    
    /*
    |--------------------------------------------------------------------------
    | RECUPERATION DE DONNEES
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère une valeur spécifique
     *
     * @param array|string $column Colonne(s) à récupérer
     *
     * @return list<mixed>|mixed La valeur demandée (valeur simple pour string, tableau associatif pour array)
     * 
     * @example
     * // Récupérer une valeur simple
     * $email = $builder->where('id', 1)->value('email');
     * 
     * // Récupérer plusieurs valeurs
     * ['name' => 'John', 'email' => 'john@example.com'] = $builder->where('id', 1)->value(['name', 'email']);
    * 
    * // Avec une expression
    * $builder->select(Expression('MAX(`batch`) AS max_batch'));
    * $maxBatch = $builder->value('max_batch');
     */
    public function value(array|string $column)
    {
        $columns = (array) $column;
        $isSingle = is_string($column);
        
        $this->ensureColumnsSelected($columns);
        
        $row = $this->first(PDO::FETCH_OBJ);
        
        if ($row === null) {
            return $isSingle ? null : array_fill_keys($columns, null);
        }
        
        $values = [];
        foreach ($columns as $col) {
            $values[] = $this->getColumnValue($row, $col);
        }
        
        return $isSingle ? $values[0] : array_combine($columns, $values);
    }

    /**
     * Récupère plusieurs valeurs (pluck-like)
     *
     * @param array|string $column Colonne(s) à récupérer
     *
     * @return list<mixed> Tableau des valeurs
     * 
     * @example
     * // Récupérer une liste de noms
     * $names = $builder->orderBy('name')->values('name');
     * // ['John', 'Jane', 'Bob']
     * 
     * // Récupérer plusieurs colonnes
     * $users = $builder->values(['id', 'name']);
     * // [
     * //     ['id' => 1, 'name' => 'John'],
     * //     ['id' => 2, 'name' => 'Jane'],
     * // ]
     */
    public function values(array|string $column): array
    {
        $columns = (array) $column;
        $isSingle = is_string($column);
        
        $this->ensureColumnsSelected($columns);
        
        $rows = $this->all(PDO::FETCH_OBJ);
        
        if ($rows === []) {
            return [];
        }
        
        $results = [];
        foreach ($rows as $row) {
            if ($isSingle) {
                $results[] = $this->getColumnValue($row, $column);
            } else {
                $values = [];
                foreach ($columns as $col) {
                    $values[$col] = $this->getColumnValue($row, $col);
                }
                $results[] = $values;
            }
        }
        
        return $results;
    }

    /**
     * Récupère plusieurs valeurs par lots pour les grands jeux de résultats (support de la pagination)
     */
    public function valuesChunked(array|string $column, int $chunkSize = 1000): Generator
    {
        $columns = (array) $column;
        $isSingle = is_string($column);
        
        $this->ensureColumnsSelected($columns);
        
        $page           = 1;
        $originalLimit  = $this->limit;
        $originalOffset = $this->offset;

        try {
            while (true) {
                $chunk = $this->clone()->forPage($page, $chunkSize)->all(PDO::FETCH_OBJ);
                
                if ($chunk === []) {
                    break;
                }
                
                foreach ($chunk as $row) {
                    if ($isSingle) {
                        yield $this->getColumnValue($row, $column);
                    } else {
                        $values = [];
                        foreach ($columns as $col) {
                            $values[$col] = $this->getColumnValue($row, $col);
                        }
                        yield $values;
                    }
                }
                
                if (count($chunk) < $chunkSize) {
                    break;
                }
                
                $page++;
            }
        } finally {
            // Restaurer les limites originales
            $this->limit = $originalLimit;
            $this->offset = $originalOffset;
        }
    }

    /**
     * 
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $result = [];
        
        $this->ensureColumnsSelected([$column, $key]);
        
        $rows = $this->all(PDO::FETCH_OBJ);
        
        foreach ($rows as $row) {
            $value = $this->getColumnValue($row, $column);
            
            if ($key !== null) {
                $keyValue = $this->getColumnValue($row, $key);
                $result[$keyValue] = $value;
            } else {
                $result[] = $value;
            }
        }
        
        return $result;
    }

    /**
     * S'assure que les colonnes demandées sont sélectionnées
     *
     * @param array $columns Colonnes à vérifier/ajouter
     */
    protected function ensureColumnsSelected(array $columns): void
    {
        $missing = $this->getMissingColumns($columns);
    
        if ($missing !== []) {
            $this->select($missing);
        }
    }

    /**
     * Récupère les colonnes manquantes dans la sélection courante
     *
     * @param array $requestedColumns Colonnes demandées
     * 
     * @return array Colonnes à ajouter
     */
    protected function getMissingColumns(array $requestedColumns): array
    {
        $missing = [];
        $selected = $this->getSelectedColumnsMap();
        
        foreach ($requestedColumns as $column) {
            if (!$this->isColumnSelected($column, $selected)) {
                $missing[] = $column;
            }
        }
        
        return $missing;
    }

    /**
     * Récupère les informations sur les colonnes sélectionnées
     *
     * Structure retournée :
     * - columns: Noms de colonnes exacts (inclut les noms simples extraits des colonnes avec table)
     * - aliases: Alias définis explicitement (avec AS)
     * - expressions: Alias des expressions SQL
     * - raw: Colonnes brutes pour les cas particuliers
     *
     * @return array{
     *     columns: array<string, true>,
     *     aliases: array<string, string>,
     *     expressions: array<string, string>,
     *     raw: array<int, string>
     * }
     */
    protected function getSelectedColumnsMap(): array
    {
        if ($this->selectedColumnsCache !== []) {
            return $this->selectedColumnsCache;
        }

        $info = [
            'columns'     => [],   // Noms de colonnes exacts
            'aliases'     => [],   // Alias définis
            'expressions' => [],   // Alias des expressions
            'raw'         => [],   // Colonnes brutes (pour débogage)
            'original'    => [],   // Mapping nom_normalisé => nom_original
        ];
        
        foreach ($this->columns as $column) {
            if ($column instanceof Expression) {
                // Analyser l'expression pour en extraire l'alias
                $expression = (string) $column;
                $alias      = Utils::extractAlias($expression);
                
                if ($alias) {
                    $normalizedAlias                       = $this->db->normalizeIdentifier($alias);
                    $info['expressions'][$normalizedAlias] = $expression;
                    $info['columns'][$normalizedAlias]     = true;
                    $info['original'][$normalizedAlias]    = $alias;
                } else {
                    // Expression sans alias, difficile à référencer
                    $info['raw'][] = $expression;
                }

                continue;
            }
            
            if (is_string($column)) {
                $column = trim($column);
                $info['raw'][] = $column;

                // Gestion des alias
                if ($alias = Utils::extractAlias($column)) {
                    $normalizedAlias = $this->db->normalizeIdentifier($alias);
                    $info['aliases'][$normalizedAlias] = $column;
                    $info['columns'][$normalizedAlias] = true;
                    $info['original'][$normalizedAlias] = $alias;
                    continue;
                }
                
                // Colonne simple ou avec table (users.name)
                $normalizedColumn = $this->db->normalizeIdentifier($column);
                $info['columns'][$normalizedColumn] = true;
                $info['original'][$normalizedColumn] = $column;
                
                // Extraire le nom simple pour les colonnes avec point
                if (str_contains($normalizedColumn, '.')) {
                    $parts                        = explode('.', $normalizedColumn);
                    $simpleName                   = end($parts);
                    $info['columns'][$simpleName] = true;
                }
            }
        }
        
        return $this->selectedColumnsCache = $info;
    }

    /**
     * Vérifie si une colonne est déjà sélectionnée
     *
     * @param array{
     *     columns: array<string, true>,
     *     aliases: array<string, string>,
     *     expressions: array<string, string>
     * } $selected Informations sur les colonnes sélectionnées
     * 
     * @return bool
     */
    protected function isColumnSelected(string $column, array $selected): bool
    {
        // Si * est sélectionné, tout est sélectionné
        if (in_array('*', $this->columns, true)) {
            return true;
        }
    
        // Vérifier dans les colonnes exactes
        if (isset($selected['columns'][$column])) {
            return true;
        }
    
        // Vérifier dans les alias
        if (isset($selected['aliases'][$column])) {
            return true;
        }
    
        // Vérifier dans les expressions (alias)
        if (isset($selected['expressions'][$column])) {
            return true;
        }
    
        // Vérifier si c'est un alias d'expression par correspondance de nom
        foreach ($selected['expressions'] as $alias => $expr) {
            if ($alias === $column) {
                return true;
            }
            // Vérifier si la colonne recherchée correspond à l'alias
            if (str_ends_with($alias, '_' . $column) || str_ends_with($column, '_' . $alias)) {
                return true;
            }
        }
    
        // Vérifier les colonnes avec préfixe de table
        if (str_contains($column, '.')) {
            $parts      = explode('.', $column);
            $simpleName = end($parts);
            if (isset($selected['columns'][$simpleName])) {
                return true;
            }
            if (isset($selected['aliases'][$simpleName])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Récupère la valeur d'une colonne depuis un objet résultat
    * 
    * @return mixed La valeur extraite ou null
     */
    protected function getColumnValue(object $row, string $column): mixed
    {
        // Tentative 1: Accès direct par propriété
        if (property_exists($row, $column)) {
            return $row->{$column};
        }

        $selected = $this->getSelectedColumnsMap();

        // Tentative 2: Colonne avec table (ex: users.name)
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $simpleName = end($parts);
            if (property_exists($row, $simpleName)) {
                return $row->{$simpleName};
            }

            // Vérifier si le nom simple est un alias
            if (isset($selected['aliases'][$simpleName]) && property_exists($row, $simpleName)) {
                return $row->{$simpleName};
            }
        }
        
        // Tentative 3: Cherchons dans les alias
        foreach ($selected['aliases'] as $alias => $original) {
            if ($alias === $column && property_exists($row, $alias)) {
                return $row->{$alias};
            }
        }
        
        // Tentative 4: Cherchons dans les expressions
        foreach ($selected['expressions'] as $alias => $expr) {
            if ($alias === $column && property_exists($row, $alias)) {
                return $row->{$alias};
            }
        }
        
        // Tentative 5: Cherchons une propriété avec le nom normalisé (sans guillemets)
        $normalized = trim($column, '`"\''); 
        if (property_exists($row, $normalized)) {
            return $row->{$normalized};
        }

        // Tentative 6: Chercher une propriété qui correspond au nom simple
        // Utile pour les cas où le driver retourne des noms de colonnes avec préfixes
        foreach (get_object_vars($row) as $prop => $value) {
            // Correspondance exacte
            if ($prop === $column) {
                return $value;
            }
            
            // Correspondance partielle (pour les alias avec suffixe. ex: "max_batch" et "batch")
            if (str_ends_with($prop, '_' . $column)) {
                return $value;
            }
            
            // Correspondance avec underscore remplaçant le point, le driver peut retourner "tablename_columnname"
            $normalized = str_replace('.', '_', $column);
            if ($prop === $normalized) {
                return $value;
            }
            
            // Pour les alias d'expressions avec guillemets
            $quotedColumn = '`' . $column . '`';
            if ($prop === $quotedColumn) {
                return $value;
            }
        }
        
        return null; 
    }

    /**
     * Vide le cache des colonnes selectionnées
     */
    protected function clearSelectedColumnsCache(): void
    {
        $this->selectedColumnsCache = [];
    }
}
