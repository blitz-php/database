<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Utils;
use Closure;

class JoinClause
{
    /**
     * Conditions de la jointure
     */
    protected array $conditions = [];

    /**
     * Bindings pour les conditions
     */
    protected array $bindings = [];

    /**
     * Opérateurs supportés
     */
    protected array $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

    /**
     * @param string $type  Type de jointure
     * @param string $table Table à joindre
     */
    public function __construct(protected BaseConnection $db, protected string $type, protected string $table)
    {
    }

    /**
     * Ajoute une condition ON avec AND
     */
    public function on(string|Closure $first, ?string $operator = null, ?string $second = null, string $boolean = 'and'): self
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        return $this->addCondition([
            'type' => 'basic',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean
        ]);
    }

    /**
     * Ajoute une condition ON avec OR
     */
    public function orOn(string|Closure $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * Ajoute une condition supplémentaire sur la jointure
     */
    public function where(string|Closure $first, ?string $operator = null, $value = null, string $boolean = 'and'): self
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return $this->addCondition([
            'type' => 'where',
            'first' => $first,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean
        ]);
    }

    /**
     * Ajoute une condition WHERE avec OR
     */
    public function orWhere(string|Closure $first, ?string $operator = null, $value = null): self
    {
        return $this->where($first, $operator, $value, 'or');
    }

    /**
     * Ajoute une condition WHERE IN
     */
    public function whereIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->addCondition([
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => false
        ]);
    }

    /**
     * Ajoute une condition WHERE NOT IN
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        return $this->addCondition([
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
            'not' => true
        ]);
    }

    /**
     * Ajoute une condition WHERE NULL
     */
    public function whereNull(string $column, string $boolean = 'and'): self
    {
        return $this->addCondition([
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => false
        ]);
    }

    /**
     * Ajoute une condition WHERE NOT NULL
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->addCondition([
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
            'not' => true
        ]);
    }

    /**
     * Ajoute une condition imbriquée
     */
    protected function whereNested(Closure $callback, string $boolean = 'and'): self
    {
        $join = new static($this->db, $this->type, $this->table);
        $callback($join);

        if (count($join->conditions)) {
            $this->addCondition([
                'type' => 'nested',
                'join' => $join,
                'boolean' => $boolean
            ]);
        }

        return $this;
    }

    /**
     * Récupère le type de jointure
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Récupère la table
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Récupère les conditions
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Récupère les bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Ajoute une condition et formate les noms de colonnes
     */
    protected function addCondition(array $condition): self
    {
        foreach (['first', 'second', 'column'] as $item) {
            if (isset($condition[$item]) && is_string($condition[$item])) {
                $condition[$item] = Utils::formatQualifiedColumn($this->db, $condition[$item]);
            }
        }

        $this->conditions[] = $condition;

        return $this;
    }
}
