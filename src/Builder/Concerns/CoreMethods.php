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
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Utils;
use BlitzPHP\Utilities\Iterable\Collection;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

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

        if ($value instanceof Expression) {
            return $this->addCondition('wheres', 'basic', [
                'column'   => $column,
                'operator' => $operator,
                'value'    => $value,
                'boolean'  => $boolean
            ]);
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

        return $this->addCondition('wheres', 'basic', [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean
        ]);
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

        return $this->addCondition('wheres', 'in', [
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'operator' => $not ? 'NOT IN' : 'IN',
        ]);
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

        $this->addCondition('wheres', 'insub', [
            'column' => $column,
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not,
        ]);

        $this->bindings->merge($query->bindings);

        return $this;
    }

    /**
     * Ajoute une clause WHERE BETWEEN
     */
    public function whereBetween(string $column, $value1, $value2, string $boolean = 'and', bool $not = false): static
    {
        return $this->addCondition('wheres', 'between', [
            'column' => $column, 
            'values' => [$value1, $value2],
            'boolean' => $boolean,
            'not' => $not,
        ]);
    }

    /**
     * Ajoute une clause WHERE BETWEEN COLUMNS
     */
    public function whereBetweenColumns(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException("whereBetweenColumns requires an array with exactly 2 columns");
        }

        return $this->addCondition('wheres', 'betweencolumns', [
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => $not,
        ]);
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
    public function whereNull(array|string $column, string $boolean = 'and', bool $not = false): static
    {
        if (is_array($column)) {
            foreach ($column as $value) {
                $this->whereNull($value, $boolean, $not);
            }

            return $this;
        }

        return $this->addCondition('wheres', 'null', [
            'column' => $column, 
            'boolean' => $boolean,
            'not' => $not,
        ]);
    }

    /**
     * Ajoute une clause WHERE NOT NULL
     */
    public function whereNotNull(array|string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE NULL avec OR
     */
    public function orWhereNull(array|string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT NULL avec OR
     */
    public function orWhereNotNull(array|string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Ajoute une clause WHERE LIKE
     */
    public function whereLike(string $column, string $value, string $boolean = 'and', bool $not = false, bool $caseSensitive = false, string $side = 'both'): static
    {
        $operator = $not ? 'NOT LIKE' : 'LIKE';
        
        if ($caseSensitive && $this->db->getDriver() === 'pgsql') {
            $operator = $not ? 'NOT ILIKE' : 'ILIKE';
        } elseif ($caseSensitive && $this->db->getDriver() === 'mysql') {
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

        return $this->addCondition('wheres', 'basic', [
            'column' => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ]);
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

        return $this->addCondition('wheres', 'exists', [
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not,
        ]);
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
    public function whereColumn(array|string $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): static
    {
        if (is_array($first)) {
            return $this->whereArray($first, $operator ?? $boolean, false, 'whereColumn');
        }

        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        return $this->addCondition('wheres', 'column', [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ]);
    }

    /**
     * Ajoute une clause WHERE Column avec OR
     */
    public function orWhereColumn(array|string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->whereColumn($first, $operator, $second, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT Column
     */
    public function whereNotColumn(array|string $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): static
    {
        if (is_array($first)) {
            return $this->whereArray($first, $operator ?? $boolean, true, 'whereColumn');
        }

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
    public function orWhereNotColumn(array|string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->whereNotColumn($first, $operator, $second, 'or');
    }

    /**
     * Ajoute une clause WHERE ANY (MySQL) / WHERE column = ANY (PostgreSQL)
     */
    public function whereAny(string $column, string $operator, array $values, string $boolean = 'and'): static
    {
        return $this->addCondition('wheres', 'any', [
            'column' => $column,
            'operator' => $operator,
            'values' => $values,
            'boolean' => $boolean,
        ]);
    }

    /**
     * Ajoute une clause WHERE ALL (MySQL) / WHERE column = ALL (PostgreSQL)
     */
    public function whereAll(string $column, string $operator, array $values, string $boolean = 'and'): static
    {
        return $this->addCondition('wheres', 'all', [
            'column' => $column,
            'operator' => $operator,
            'values' => $values,
            'boolean' => $boolean,
        ]);
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
        return $this->addCondition('wheres', 'valuebetween', [
            'value' => $value,
            'column1' => $column1,
            'column2' => $column2,
            'boolean' => $boolean,
            'not' => $not
        ]);
    }
    
    /**
     * Add another query builder as a nested where to the query builder.
     */
    public function addNestedWhereQuery(BaseBuilder $query, string $boolean = 'and'): static
    {
        if (count($query->wheres)) {
            $this->wheres[] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => $boolean
            ];

            $this->bindings->merge($query->bindings);
        }

        return $this;
    }

    /**
     * Ajoute une clause HAVING
     * 
     * Supporte les signatures :
     * - having(string $column, string $operator, mixed $value)
     * - having(string $column, mixed $value) // operator = '='
     * - having(array $conditions)
     * 
     * @param array|Closure|Expression|string $column
     */
    public function having($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure) {
            return $this->havingNested($column, $boolean);
        }

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

        if ($value instanceof Expression) {
            return $this->addCondition('havings', 'basic', [
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'boolean' => $boolean
            ]);
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

        return $this->addCondition('havings', 'basic', [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ])->asCrud('select');
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
    public function havingIn(string $column, array|Closure $values, string $boolean = 'and', bool $not = false): static
    {
        if ($values instanceof Closure) {
            return $this->havingInSub($column, $values, $boolean, $not);
        }

        return $this->addCondition('havings', 'in', [
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'operator' => $not ? 'NOT IN' : 'IN'
        ]);
    }

    /**
     * Ajoute une clause HAVING NOT IN
     */
    public function havingNotIn(string $column, array|Closure $values, string $boolean = 'and'): static
    {
        return $this->havingIn($column, $values, $boolean, true);
    }

    /**
     * Ajoute une clause HAVING IN avec OR
     */
    public function orHavingIn(string $column, array|Closure $values): static
    {
        return $this->havingIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause HAVING NOT IN avec OR
     */
    public function orHavingNotIn(string $column, array|Closure $values): static
    {
        return $this->havingNotIn($column, $values, 'or');
    }

    /**
     * Ajoute une clause HAVING IN avec une sous-requête
     */
    public function havingInSub(string $column, Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        $this->addCondition('havings', 'insub', [
            'column' => $column,
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not
        ]);

        $this->bindings->merge($query->bindings);

        return $this;
    }
    
    /**
     * Ajoute une clause HAVING BETWEEN
     */
    public function havingBetween(string $column, $value1, $value2, string $boolean = 'and', bool $not = false): static
    {
        return $this->addCondition('havings', 'between', [
            'column' => $column,
            'values' => [$value1, $value2],
            'boolean' => $boolean,
            'not' => $not
        ]);
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
        return $this->addCondition('havings', 'null', [
            'column' => $column,
            'boolean' => $boolean,
            'not' => $not
        ]);
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
    public function havingLike(string $column, string $value, string $boolean = 'and', bool $not = false, bool $caseSensitive = false, string $side = 'both'): static
    {
        $operator = $not ? 'NOT LIKE' : 'LIKE';
        
        if ($caseSensitive && $this->db->getDriver() === 'pgsql') {
            $operator = $not ? 'NOT ILIKE' : 'ILIKE';
        } elseif ($caseSensitive && $this->db->getDriver() === 'mysql') {
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

        return $this->addCondition('havings', 'basic', [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ]);
    }

    /**
     * Ajoute une clause HAVING NOT LIKE
     */
    public function havingNotLike(string $column, string $value, string $boolean = 'and', bool $caseSensitive = false, string $side = 'both'): static
    {
        return $this->havingLike($column, $value, $boolean, true, $caseSensitive, $side);
    }

    /**
     * Ajoute une clause HAVING LIKE avec OR
     */
    public function orHavingLike(string $column, string $value, bool $caseSensitive = false, string $side = 'both'): static
    {
        return $this->havingLike($column, $value, 'or', false, $caseSensitive, $side);
    }

    /**
     * Ajoute une clause HAVING NOT LIKE avec OR
     */
    public function orHavingNotLike(string $column, string $value, bool $caseSensitive = false, string $side = 'both'): static
    {
        return $this->havingNotLike($column, $value, 'or', $caseSensitive, $side);
    }

    /**
     * Ajoute une clause HAVING EXISTS
     */
    public function havingExists(Closure $callback, string $boolean = 'and', bool $not = false): static
    {
        $query = $this->newQuery();
        $callback($query);

        return $this->addCondition('havings', 'exists', [
            'query' => $query,
            'boolean' => $boolean,
            'not' => $not
        ]);
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
            return $this->legacyJoin($table, $first, $operator ?? $type);
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
     * 
     * @param (Closure(JoinClause): void) $callback
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
            return $this->addCondition('orders', [
                'column' => $column,
                'direction' => '',
                'raw' => true
            ])->asCrud('select');
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

        return $this->addCondition('orders', [
            'column'    => $column,
            'direction' => in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '',
            'raw'       => false
        ])->asCrud('select');
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
     * Get an array with all orders with a given column removed.
     */
    protected function removeExistingOrdersFor(string $column): array
    {
        $column = $this->buildColumnName($column);

        return (new Collection($this->orders))
            ->reject(fn ($order) => isset($order['column']) && $order['column'] === $column)
            ->values()
            ->all();
    }

    /**
     * Throw an exception if the query doesn't have an orderBy clause.
     *
     * @throws RuntimeException
     */
    protected function enforceOrderBy(): void
    {
        if ($this->orders === []) {
            throw new RuntimeException('You must specify an orderBy clause when using this function.');
        }
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

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Traite un tableau de conditions WHERE
     */
    protected function whereArray(array $conditions, string $boolean = 'and', bool $not = false, string $function = 'where'): static
    {
        if (! in_array($function, ['where', 'whereColumn', 'whereDate'])) {
            $function = 'where';
        }

        foreach ($conditions as $key => $val) {
            if (is_int($key)) {
                // Condition brute
                $this->whereRaw($val, [], $boolean);
            } elseif (is_array($val) && count($val) === 2) {
                // [$operator, $value]
                $val[0] = $not ? $this->invertOperator($val[0]) : $val[0];
                $this->{$function}($key, $val[0], $val[1], $boolean);
            } else {
                [$key, $operator, $val] = $this->normalizeWhereParameters($key, $val, null);
                $operator = $not ? $this->invertOperator($operator) : $operator;
                $this->{$function}($key, $operator, $val, $boolean);
            }
        }

        return $this;
    }

    /**
     * Ajoute une clause HAVING imbriquée
     */
    protected function havingNested(Closure $callback, string $boolean = 'and'): static
    {
        $query = $this->newQuery();
        $callback($query);

        if (count($query->havings)) {
            $this->havings[] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => $boolean
            ];
        }

        return $this;
    }
    
    protected function addCondition(string $property, array|string $type, ?array $condition = null): self
    {
        if (! in_array($property, ['wheres', 'havings', 'orders'], true)) {
            throw new InvalidArgumentException();
        }

        if (is_array($type)) {
            $condition = $type;
            $type = null;
        }

        if ($type !== null) {
            $condition['type'] = $type;
        }
        
        foreach (['column', 'column1', 'column2', 'first', 'second'] as $column) {
            if (isset($condition[$column]) && is_string($condition[$column])) {
                $condition[$column] = $this->buildColumnName($condition[$column]);
            }
        }

        // Ajouter les bindings dans le contexte approprié
        $context = match($property) {
            'wheres'  => 'where',
            'havings' => 'having',
            'orders'  => 'order',
            default   => 'where'
        };
        
        if (isset($condition['values'])) {
            $this->bindings->addMany($condition['values'], $context);
        } else if (isset($condition['value']) && !$condition['value'] instanceof Expression) {
            $this->bindings->add($condition['value'], $context);
        }

        $this->{$property}[] = $condition;

        return $this;
    }

    /**
     * Normalise les paramètres de where
     */
    protected function normalizeWhereParameters(mixed $column, mixed $operator, mixed $value): array
    {
        if ($value === null) {
            // Si seulement 2 paramètres sont fournis, le deuxième est la valeur
            if ($operator !== null) {
                $value    = $operator;
                [$column, $operator] = Utils::extractOperatorFromColumn($column, $operator);
            } else if (null !== $parsed = Utils::parseExpression($column)) {
                [$column, $operator, $value] = $parsed; 
            }
        }

        if ($operator !== null) {
            $operator = Utils::translateOperator($operator);
        }

        return [$column, $operator, $value];
    }

    /**
     * Normalise les opérateurs personnalisés
     * 
     * @deprecated use Utils::translateOperator() instead
     */
    protected function normalizeOperator(string $operator): string
    {
        return Utils::translateOperator($operator);
    }

    /**
     * Inverse un opérateur
     */
    protected function invertOperator(string $operator): string
    {
        return Utils::invertOperator($operator);
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
                    [$key, $operator, $value] = $this->normalizeWhereParameters($key, $value, null);
                    
                    $join->on($this->buildColumnName($key), 
                        $operator, 
                        $this->buildColumnName($value),
                        $key[0] === '|' ? 'or' : 'and'  
                    );
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
        $driver = $this->db->getDriver();
        
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
