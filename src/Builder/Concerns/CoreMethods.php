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

use BlitzPHP\Database\Builder\JoinClause;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Contracts\Database\BuilderInterface;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait CoreMethods
{
    /**
     * Liste des conditions WHERE
     */
    protected array $wheres = [];

    /**
     * Liste des conditions HAVING
     */
    protected array $havings = [];
    
    /**
     * Propriété pour stocker les clauses ORDER BY
     */
    protected array $orders = [];

    /**
     * Propriété pour stocker les clauses GROUP BY
     */
    protected array $groups = [];

    /*
    |--------------------------------------------------------------------------
    | WHERE CLAUSES
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une clause WHERE
     * Supporte les signatures :
     * - where(string $column, string $operator, mixed $value)
     * - where(string $column, mixed $value) // operator = '='
     * - where(array $conditions)
     * - where(Closure $callback)
     * 
     * @param array|Closure|Expression|string $column
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (is_array($column)) {
            return $this->whereArray($column, $boolean);
        }

        // Normalisation des paramètres selon le nombre d'arguments
        [$column, $operator, $value] = $this->normalizeWhereParameters($column, $operator, $value);

        // Traitement spécial pour les valeurs NULL
        if ($value === null) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        // Gestion des opérateurs spéciaux
        $operator = $this->normalizeOperator($operator);

        if ($value instanceof Expression) {
            $this->wheres[] = [
                'type'     => 'basic',
                'column'   => $column,
                'operator' => $operator,
                'value'    => $value,
                'boolean'  => $boolean
            ];

            return $this;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (in_array($operator, ['IN', 'NOT IN', '@', '!@'])) {
            $values = is_array($value) ? $value : [$value];

            return $this->whereIn($column, $values, $boolean, $operator === 'NOT IN' || $operator === '!@');
        }

        if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
            if (! is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException("BETWEEN requires an array with exactly 2 values");
            }

            return $this->whereBetween($column, $value[0], $value[1], $boolean, $operator === 'NOT BETWEEN');
        }

        if (in_array($operator, ['LIKE', 'NOT LIKE', '%', '!%'])) {
            return $this->whereLike($column, $value, $boolean, $operator === 'NOT LIKE' || $operator === '!%', false);
        }

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $this->buildColumnName($column),
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean
        ];

        $this->bindings->add($value);

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT
     */
    public function whereNot($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->whereNot($key, '=', $val, $boolean);
            }

            return $this;
        }

        [$column, $operator, $value] = $this->normalizeWhereParameters($column, $operator, $value);
        
        // Inverser l'opérateur
        $operator = $this->invertOperator($operator);
        
        return $this->where($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE avec OR
     */
    public function orWhere($column, $operator = null, $value = null): static
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT avec OR
     */
    public function orWhereNot($column, $operator = null, $value = null): static
    {
        return $this->whereNot($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE IN
     */
    public function whereIn(string $column, array|Closure $values, string $boolean = 'and', bool $not = false): static
    {
        if ($values instanceof Closure) {
            return $this->whereInSub($column, $values, $boolean, $not);
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $this->buildColumnName($column),
            'values' => $values,
            'boolean' => $boolean,
            'operator' => $not ? 'NOT IN' : 'IN'
        ];

        foreach ($values as $value) {
            $this->bindings->add($value);
        }

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT IN
     */
    public function whereNotIn(string $column, array|Closure $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE IN avec OR
     */
    public function orWhereIn(string $column, array|Closure $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT IN avec OR
     */
    public function orWhereNotIn(string $column, array|Closure $values): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause WHERE IN avec une sous-requête
     */
    public function whereInSub(string $column, Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = [
            'type' => 'insub',
            'column' => $this->buildColumnName($column),
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE BETWEEN
     */
    public function whereBetween(string $column, $value1, $value2, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' =>$this->buildColumnName($column),
            'values' => [$value1, $value2],
            'boolean' => $boolean,
            'not' => $not
        ];

        $this->bindings->add($value1);
        $this->bindings->add($value2);

        return $this;
    }

    /**
     * Ajoute une clause WHERE BETWEEN COLUMNS
     */
    public function whereBetweenColumns(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException("whereBetweenColumns requires an array with exactly 2 columns");
        }

        $this->wheres[] = [
            'type' => 'betweencolumns',
            'column' => $this->buildColumnName($column),
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT BETWEEN
     */
    public function whereNotBetween(string $column, $value1, $value2, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $value1, $value2, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE NOT BETWEEN COLUMNS
     */
    public function whereNotBetweenColumns(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetweenColumns($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE BETWEEN avec OR
     */
    public function orWhereBetween(string $column, $value1, $value2): static
    {
        return $this->whereBetween($column, $value1, $value2, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT BETWEEN avec OR
     */
    public function orWhereNotBetween(string $column, $value1, $value2): static
    {
        return $this->whereNotBetween($column, $value1, $value2, 'or');
    }

    /**
     * Ajoute une clause WHERE NULL
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $this->buildColumnName($column),
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT NULL
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE NULL avec OR
     */
    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT NULL avec OR
     */
    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Ajoute une clause WHERE LIKE
     */
    public function whereLike(string $column, string $value, string $boolean = 'and', bool $not = false, bool $caseSensitive = false, string $side = 'both'): static
    {
        $operator = $not ? 'NOT LIKE' : 'LIKE';
        
        if ($caseSensitive && $this->db->getPlatform() === 'pgsql') {
            $operator = $not ? 'NOT ILIKE' : 'ILIKE';
        } elseif ($caseSensitive && $this->db->getPlatform() === 'mysql') {
            $operator .= ' BINARY';
        }

        if (false !== $pos = strpos($value, '%')) {
            if (2 === substr_count($value, '%')) {
                $side = 'both';
            } else {
                $side = $pos === 0 ? 'before' : 'after';
            }

            $value = str_replace('%', '', $value);
        }

        $value = match($side) {
            'before' => "%{$value}",
            'after'  => "{$value}%",
            'both'   => "%{$value}%",
            default  => $value
        };

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $this->buildColumnName($column),
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean
        ];

        $this->bindings->add($value);

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT LIKE
     */
    public function whereNotLike(string $column, string $value, string $boolean = 'and', bool $caseSensitive = false): static
    {
        return $this->whereLike($column, $value, $boolean, true, $caseSensitive);
    }

    /**
     * Ajoute une clause WHERE LIKE avec OR
     */
    public function orWhereLike(string $column, string $value, bool $caseSensitive = false): static
    {
        return $this->whereLike($column, $value, 'or', false, $caseSensitive);
    }

    /**
     * Ajoute une clause WHERE NOT LIKE avec OR
     */
    public function orWhereNotLike(string $column, string $value, bool $caseSensitive = false): static
    {
        return $this->whereNotLike($column, $value, 'or', $caseSensitive);
    }

    /**
     * Ajoute une clause WHERE EXISTS
     */
    public function whereExists(Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        $this->wheres[] = [
            'type' => 'exists',
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT EXISTS
     */
    public function whereNotExists(Closure $callback, string $boolean = 'and'): static
    {
        return $this->whereExists($callback, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE EXISTS avec OR
     */
    public function orWhereExists(Closure $callback): static
    {
        return $this->whereExists($callback, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT EXISTS avec OR
     */
    public function orWhereNotExists(Closure $callback): static
    {
        return $this->whereNotExists($callback, 'or');
    }

    /**
     * Ajoute une clause WHERE sur une colonne par rapport à une autre colonne
     */
    public function whereColumn(string $first, string $operator, ?string $second = null, string $boolean = 'and'): static
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $this->buildColumnName($first),
            'operator' => $operator,
            'second' => $this->buildColumnName($second),
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE Column avec OR
     */
    public function orWhereColumn(string $first, string $operator, ?string $second = null): static
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT Column
     */
    public function whereNotColumn(string $first, string $operator, ?string $second = null, string $boolean = 'and'): static
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        // Inverser l'opérateur
        $operator = $this->invertOperator($operator);

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT Column avec OR
     */
    public function orWhereNotColumn(string $first, string $operator, ?string $second = null): static
    {
        return $this->whereNotColumn($first, $operator, $second, 'or');
    }

    /**
     * Ajoute une clause WHERE ANY (MySQL) / WHERE column = ANY (PostgreSQL)
     */
    public function whereAny(string $column, string $operator, array $values, string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type' => 'any',
            'column' => $this->buildColumnName($column),
            'operator' => $operator,
            'values' => $values,
            'boolean' => $boolean
        ];

        foreach ($values as $value) {
            $this->bindings->add($value);
        }

        return $this;
    }

    /**
     * Ajoute une clause WHERE ALL (MySQL) / WHERE column = ALL (PostgreSQL)
     */
    public function whereAll(string $column, string $operator, array $values, string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type' => 'all',
            'column' => $this->buildColumnName($column),
            'operator' => $operator,
            'values' => $values,
            'boolean' => $boolean
        ];

        foreach ($values as $value) {
            $this->bindings->add($value);
        }

        return $this;
    }

    /**
     * Ajoute une clause WHERE NONE (inverse de ANY)
     */
    public function whereNone(string $column, string $operator, array $values, string $boolean = 'and'): static
    {
        return $this->whereAny($column, $this->invertOperator($operator), $values, $boolean);
    }

    /**
     * Ajoute une clause WHERE VALUE BETWEEN (WHERE ? BETWEEN column1 AND column2)
     */
    public function whereValueBetween($value, string $column1, string $column2, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => 'valuebetween',
            'value' => $value,
            'column1' => $this->buildColumnName($column1),
            'column2' => $this->buildColumnName($column2),
            'boolean' => $boolean,
            'not' => $not
        ];

        $this->bindings->add($value);

        return $this;
    }

    /**
     * Ajoute une clause HAVING
     * 
     * Supporte les signatures :
     * - having(string $column, string $operator, mixed $value)
     * - having(string $column, mixed $value) // operator = '='
     * - having(array $conditions)
     */
    public function having($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->having($key, '=', $val, $boolean);
            }
            return $this;
        }

        [$column, $operator, $value] = $this->normalizeWhereParameters($column, $operator, $value);

        if ($value === null) {
            return $this->havingNull($column, $boolean, $operator !== '=');
        }

        $operator = $this->normalizeOperator($operator);

        if ($value instanceof Expression) {
            $this->havings[] = [
                'type' => 'basic',
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'boolean' => $boolean
            ];
            return $this;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (in_array($operator, ['IN', 'NOT IN', '@', '!@'])) {
            $values = is_array($value) ? $value : [$value];
        
            return $this->havingIn($column, $values, $boolean, $operator === 'NOT IN' || $operator === '!@');
        }

        if (in_array($operator, ['BETWEEN', 'NOT BETWEEN'])) {
            if (!is_array($value) || count($value) !== 2) {
                throw new InvalidArgumentException("BETWEEN requires an array with exactly 2 values");
            }

            return $this->havingBetween($column, $value[0], $value[1], $boolean, $operator === 'NOT BETWEEN');
        }

        if (in_array($operator, ['LIKE', 'NOT LIKE', '%', '!%'])) {
            return $this->havingLike($column, $value, $boolean, $operator === 'NOT LIKE' || $operator === '!%');
        }

        $this->havings[] = [
            'type' => 'basic',
            'column' => $this->buildColumnName($column),
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ];

        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause HAVING avec OR
     */
    public function orHaving($column, $operator = null, $value = null): static
    {
        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause HAVING IN
     */
    public function havingIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->havings[] = [
            'type' => 'in',
            'column' => $this->buildColumnName($column),
            'values' => $values,
            'boolean' => $boolean,
            'operator' => $not ? 'NOT IN' : 'IN'
        ];

        return $this;
    }

    /**
     * Ajoute une clause HAVING NOT IN
     */
    public function havingNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->havingIn($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING IN avec OR
     */
    public function orHavingIn(string $column, array $values): static
    {
        return $this->havingIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT IN avec OR
     */
    public function orHavingNotIn(string $column, array $values): static
    {
        return $this->havingNotIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause HAVING BETWEEN
     */
    public function havingBetween(string $column, $value1, $value2, string $boolean = 'and', bool $not = false): static
    {
        $this->havings[] = [
            'type' => 'between',
            'column' => $this->buildColumnName($column),
            'values' => [$value1, $value2],
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause HAVING NOT BETWEEN
     */
    public function havingNotBetween(string $column, $value1, $value2, string $boolean = 'and'): static
    {
        return $this->havingBetween($column, $value1, $value2, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING BETWEEN avec OR
     */
    public function orHavingBetween(string $column, $value1, $value2): static
    {
        return $this->havingBetween($column, $value1, $value2, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT BETWEEN avec OR
     */
    public function orHavingNotBetween(string $column, $value1, $value2): static
    {
        return $this->havingNotBetween($column, $value1, $value2, 'or');
    }

    /**
     * Ajoute une clause HAVING NULL
     */
    public function havingNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $this->havings[] = [
            'type' => 'null',
            'column' => $this->buildColumnName($column),
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause HAVING NOT NULL
     */
    public function havingNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->havingNull($column, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING NULL avec OR
     */
    public function orHavingNull(string $column): static
    {
        return $this->havingNull($column, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT NULL avec OR
     */
    public function orHavingNotNull(string $column): static
    {
        return $this->havingNotNull($column, 'or');
    }

    /**
     * Ajoute une clause HAVING LIKE
     */
    public function havingLike(string $column, string $value, string $boolean = 'and', bool $not = false): static
    {
        return $this->having($column, $not ? 'NOT LIKE' : 'LIKE', $value, $boolean);
    }

    /**
     * Ajoute une clause HAVING NOT LIKE
     */
    public function havingNotLike(string $column, string $value, string $boolean = 'and'): static
    {
        return $this->havingLike($column, $value, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING LIKE avec OR
     */
    public function orHavingLike(string $column, string $value): static
    {
        return $this->havingLike($column, $value, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT LIKE avec OR
     */
    public function orHavingNotLike(string $column, string $value): static
    {
        return $this->havingNotLike($column, $value, 'or');
    }

    /**
     * Ajoute une clause HAVING EXISTS
     */
    public function havingExists(Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        $this->havings[] = [
            'type' => 'exists',
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not
        ];

        return $this;
    }

    /**
     * Ajoute une clause HAVING NOT EXISTS
     */
    public function havingNotExists(Closure $callback, string $boolean = 'and'): static
    {
        return $this->havingExists($callback, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING EXISTS avec OR
     */
    public function orHavingExists(Closure $callback): static
    {
        return $this->havingExists($callback, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT EXISTS avec OR
     */
    public function orHavingNotExists(Closure $callback): static
    {
        return $this->havingNotExists($callback, 'or');
    }

    /*
    |--------------------------------------------------------------------------
    | JOIN CLAUSES
    |--------------------------------------------------------------------------
    */

    /**
     * Liste des jointures
     */
    protected array $joins = [];

    /**
     * Type de jointures entre tables
     */
    protected array $joinTypes = [
        'INNER', 'LEFT', 'RIGHT', 'FULL OUTER',
        'CROSS', 'LEFT OUTER', 'RIGHT OUTER',
    ];

    /**
     * Ajoute une jointure à la requête
     * Supporte les anciennes et nouvelles syntaxes
     */
    public function join(string $table, $first, ?string $operator = null, $second = null, string $type = 'INNER'): self
    {
        // Ancienne syntaxe : join(table, array|string $fields, string $type) ou avec un tableau associatif
        if ((is_string($first) && $second === null) || is_array($first)) {
            return $this->legacyJoin($table, $first, $type);
        }

        $join = new JoinClause($this->db, $type, $this->db->makeTableName($table));

        if ($first instanceof Closure) {
            $first($join);
        } elseif ($second !== null) {
            $join->on($first, $operator ?? '=', $second);
        } else {
            $join->on($first, '=', $operator);
        }

        $this->joins[] = $join;

        return $this->asCrud('select');
    }

    /**
     * Génère la partie JOIN (de type FULL OUTER) de la requête
     */
    public function fullJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'FULL OUTER');
    }

    /**
     * Génère la partie JOIN (de type INNER) de la requête
     */
    public function innerJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'INNER');
    }

    /**
     * Génère la partie JOIN (de type LEFT) de la requête
     */
    public function leftJoin(string $table, $first, ?string $operator = null, $second = null, bool $outer = false): self
    {
        $type = 'LEFT' . ($outer ? ' OUTER' : '');

        return $this->join($table, $first, $operator, $second, $type);
    }

    /**
     * Génère la partie JOIN (de type LEFT OUTER) de la requête
     */
    public function leftOuterJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->leftJoin($table, $first, $operator, $second, true);
    }

    /**
     * Génère la partie JOIN (de type RIGHT) de la requête
     */
    public function rightJoin(string $table, $first, ?string $operator = null, $second = null, bool $outer = false): self
    {
        $type = 'RIGHT' . ($outer ? ' OUTER' : '');

        return $this->join($table, $first, $operator, $second, $type);
    }

    /**
     * Génère la partie JOIN (de type RIGHT OUTER) de la requête
     */
    public function rightOuterJoin(string $table, $first, ?string $operator = null, $second = null): self
    {
        return $this->rightJoin($table, $first, $operator, $second, true);
    }

    /**
     * Génère la partie JOIN (de type CROSS JOIN) de la requête
     */
    public function crossJoin(string $table, ?Closure $first = null, ?string $operator = null, ?string $second = null): self
    {
        if ($first instanceof Closure) {
            return $this->join($table, $first, null, null, 'CROSS');
        }

        return $this->join($table, $first, $operator, $second, 'CROSS');
    }

    /**
     * Ajoute une jointure avec une sous-requête
     */
    public function joinSub(Closure|BuilderInterface $query, string $as, Closure $callback, string $type = 'INNER'): self
    {
        $subquery = $this->buildSubquery($query, true, $as);
        
        $join = new JoinClause($this->db, $type, $subquery);
        $callback($join);
        
        $this->joins[] = $join;
        
        return $this->asCrud('select');
    }

    /**
     * Ajoute une jointure avec conditions complexes
     */
    public function joinComplex(string $table, Closure $callback, string $type = 'INNER'): self
    {
        return $this->join($table, $callback, $type);
    }

    /**
     * Ajoute une jointure avec des conditions supplémentaires
     */
    public function joinWhere(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $join = new JoinClause($this->db, $type, $this->db->makeTableName($table));
        $join->on($first, $operator, $second);
        
        $this->joins[] = $join;
        
        return $this->asCrud('select');
    }

    /**
     * Ajoute une jointure LATERAL
     */
    public function joinLateral(Closure|BuilderInterface $query, string $as, Closure $callback, string $type = 'INNER'): self
    {
        $subquery = $this->buildSubquery($query, true, $as);
        $subquery = 'LATERAL ' . $subquery;
        
        $join = new JoinClause($this->db, $type, $subquery);
        $callback($join);
        
        $this->joins[] = $join;
        
        return $this->asCrud('select');
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER BY & GROUP BY
    |--------------------------------------------------------------------------
    */

    /**
     * 
     * 
     * Supporte les signatures :
     * - orderBy(string $column, string $direction = 'ASC')
     * - orderBy(array $columns, string $direction = 'ASC')
     * - orderBy(Expression $expression)
     * - orderBy(Closure $closure)
     */
    public function orderBy($column, string $direction = 'ASC'): self
    {
        $direction = strtoupper(trim($direction));
        
        if (!in_array($direction, ['ASC', 'DESC', 'RANDOM'], true)) {
            throw new InvalidArgumentException("Invalid direction: {$direction}");
        }

        if ($column instanceof Expression) {
            $this->orders[] = [
                'column' => $column,
                'direction' => '',
                'raw' => true
            ];

            return $this->asCrud('select');
        }
        if ($column instanceof Closure) {
            return $this->orderBySub($column);
        }

        if (is_array($column)) {
            return $this->orderByMultiple($column, $direction);
        }

        $column = trim($column);
        
        if ($direction === 'RANDOM') {
            return $this->orderByRandom($column);
        }

        $this->orders[] = [
            'column'    => $this->buildColumnName($column),
            'direction' => in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '',
            'raw'       => false
        ];

        return $this->asCrud('select');
    }

    /**
     * Ajoute un tri aléatoire
     */
    public function rand(?int $digit = null): self
    {
        if ($digit === null) {
            $digit = '';
        }

        return $this->orderByRandom((string) $digit);
    }

    /**
     * Ajoute plusieurs ORDER BY à la fois
     */
    public function orderByMultiple(array $orders, string $direction = 'ASC'): self
    {
        foreach ($orders as $key => $value) {
            if (is_int($key)) {
                // Tableau indexé ['col1', 'col2']
                $this->orderBy($value, $direction);
            } else {
                // Tableau associatif ['col1' => 'ASC', 'col2' => 'DESC']
                $this->orderBy($key, $value);
            }
        }
        
        return $this;
    }

    /**
     * Ajoute une clause ORDER BY avec une sous-requête
     */
    public function orderBySub(Closure|BuilderInterface $query, string $direction = 'ASC'): self
    {
        $subquery = $this->buildSubquery($query, true);
        $this->orders[] = [
            'column'    => new Expression($subquery),
            'direction' => ' ' . $direction,
            'raw'       => true
        ];
        
        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause ORDER BY NULLS FIRST/LAST (PostgreSQL)
     */
    public function orderByNulls(string $column, string $direction = 'ASC', string $nulls = 'LAST'): self
    {
        $column = $this->buildParseField($column);
        $direction = strtoupper($direction);
        $nulls = strtoupper($nulls);
        
        if (!in_array($nulls, ['FIRST', 'LAST'])) {
            $nulls = 'LAST';
        }
        
        $this->orders[] = [
            'column'    => new Expression("{$column} {$direction} NULLS {$nulls}"),
            'direction' => '',
            'raw'       => true
        ];
        
        return $this->asCrud('select');
    }

    /**
     * Réinitialise les clauses ORDER BY
     */
    public function reorder(?string $column = null, string $direction = 'ASC'): self
    {
        $this->orders = [];
        
        if ($column !== null) {
            $this->orderBy($column, $direction);
        }
        
        return $this;
    }

    /**
     * Ajoute une clause ORDER BY en dernier
     */
    public function orderByAppend(string $column, string $direction = 'ASC'): self
    {
        return $this->orderBy($column, $direction);
    }

    /**
     * Ajoute une clause ORDER BY en premier
     */
    public function orderByPrepend(string $column, string $direction = 'ASC'): self
    {
        $column = $this->buildParseField($column);
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '';
        
        $order = [
            'column' => $this->buildColumnName($column),
            'direction' => $direction,
            'raw' => false
        ];
        
        array_unshift($this->orders, $order);
        
        return $this->asCrud('select');
    }

    /**
     * Supprime toutes les clauses ORDER BY
     */
    public function withoutOrder(): self
    {
        $this->orders = [];
        
        return $this;
    }

    /**
     * Vérifie si une clause ORDER BY existe
     */
    public function hasOrder(): bool
    {
        return $this->orders !== [];
    }

    /**
     * Récupère toutes les clauses ORDER BY
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * Ajoute une clause GROUP BY
     * 
     * Supporte les signatures :
     * - groupBy(string|array $column)
     * - groupBy(Expression $expression)
     */
    public function groupBy($column): self
    {
        if ($column instanceof Expression) {
            $this->groups[] = $column;
            
            return $this->asCrud('select');
        }

        if (is_array($column)) {
            foreach ($column as &$val) {
                $val = $this->buildColumnName($val);
            }
            $columns = implode(',', $column);
        } else {
            $columns = $this->buildColumnName($column);
        }

        $this->groups[] = $columns;

        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause GROUP BY avec une sous-requête
     */
    public function groupBySub(Closure|BuilderInterface $query): self
    {
        $subquery = $this->buildSubquery($query, true);
        $this->groups[] = new Expression($subquery);
        
        return $this->asCrud('select');
    }

    /**
     * Supprime toutes les clauses GROUP BY
     */
    public function withoutGroup(): self
    {
        $this->groups = [];
        
        return $this;
    }

    /**
     * Vérifie si une clause GROUP BY existe
     */
    public function hasGroup(): bool
    {
        return $this->groups !== [];
    }

    /**
     * Récupère toutes les clauses GROUP BY
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /*
    |--------------------------------------------------------------------------
    | PROTECTED METHODS
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une clause WHERE imbriquée
     */
    protected function whereNested(Closure $callback, string $boolean = 'and'): static
    {
        $query = $this->newQuery();
        $callback($query);

        if (count($query->wheres)) {
            $this->wheres[] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => $boolean
            ];
        }

        return $this;
    }

    /**
     * Traite un tableau de conditions WHERE
     */
    protected function whereArray(array $conditions, string $boolean = 'and'): static
    {
        foreach ($conditions as $key => $val) {
            if (is_int($key)) {
                // Condition brute
                $this->whereRaw($val, [], $boolean);
            } elseif (is_array($val) && count($val) === 2) {
                // [$operator, $value]
                $this->where($key, $val[0], $val[1], $boolean);
            } else {
                // [$value] avec opérateur par défaut '='
                $this->where($key, '=', $val, $boolean);
            }
        }

        return $this;
    }

    /**
     * Normalise les paramètres de where
     */
    protected function normalizeWhereParameters(mixed $column, mixed $operator, mixed $value): array
    {
        // Si seulement 2 paramètres sont fournis, le deuxième est la valeur
        if ($value === null && $operator !== null) {
            $value    = $operator;
            $operator = '=';
        }

        return [$column, $operator, $value];
    }

    /**
     * Normalise les opérateurs personnalisés
     */
    protected function normalizeOperator(string $operator): string
    {
        return match($operator) {
            '%'     => 'LIKE',
            '!%'    => 'NOT LIKE',
            '@'     => 'IN',
            '!@'    => 'NOT IN',
            default => $operator
        };
    }

    /**
     * Inverse un opérateur
     */
    protected function invertOperator(string $operator): string
    {
        return match($operator) {
            '='         => '!=',
            '!='        => '=',
            '<'         => '>=',
            '>'         => '<=',
            '<='        => '>',
            '>='        => '<',
            'LIKE', '%' => 'NOT LIKE',
            'NOT LIKE', '!%' => 'LIKE',
            'IN', '@'   => 'NOT IN',
            'NOT IN', '!@' => 'IN',
            default     => $operator
        };
    }

    /**
     * Support de l'ancienne syntaxe de jointure
     */
    protected function legacyJoin(string $table, array|string $fields, string $type = 'INNER'): self
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, $this->joinTypes, true)) {
            $type = 'INNER';
        }

        $join = new JoinClause($this->db, $type, $this->db->makeTableName($table));

        if (is_string($fields)) {
            // Format: "users.id=posts.user_id"
            if (str_contains($fields, '=')) {
                [$first, $second] = explode('=', $fields, 2);
                $join->on($this->buildColumnName($first), '=', $this->buildColumnName($second));
            } else {
                // Format: "id" (pour jointure naturelle implicite)
                $currentTable = $this->from;
                $join->on($this->buildColumnName($currentTable . '.' . $fields), '=', $this->buildColumnName($table . '.' . $fields));
            }
        } elseif (is_array($fields)) {
            foreach ($fields as $key => $value) {
                if (is_int($key)) {
                    // Liste simple de conditions
                    $join->on($this->buildColumnName($value), '=', $this->buildColumnName($value));
                } else {
                    // Tableau associatif
                    $join->on($this->buildColumnName($key), '=', $this->buildColumnName($value));
                }
            }
        }

        $this->joins[] = $join;

        return $this;
    }

    /**
     * Gère le tri aléatoire
     */
    protected function orderByRandom(string $column): self
    {
        $driver = $this->db->getPlatform();
        
        // Si le champ est numérique, c'est une seed
        if (ctype_digit($column)) {
            $seed = (int) $column;
            
            if ($driver === 'mysql') {
                $column = "RAND({$seed})";
            } elseif ($driver === 'pgsql') {
                // Pour PostgreSQL, on utilise SET SEED d'abord
                $this->db->query("SELECT setseed({$seed})");
                $column = "RANDOM()";
            } else {
                $column = "RANDOM()";
            }
        } else {
            $column = $driver === 'mysql' ? 'RAND()' : 'RANDOM()';
        }

        $this->orders[] = [
            'column'    => new Expression($column),
            'direction' => '',
            'raw'       => true
        ];

        return $this;
    }
}
