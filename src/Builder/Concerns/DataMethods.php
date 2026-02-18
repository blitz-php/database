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
use BlitzPHP\Database\Result\BaseResult;
use Closure;

/**
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait DataMethods
{
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
        $column = $this->buildColumnName($column);

        if ($builder->distinct || $builder->hasGroup()) {
            $builder = $builder->fromSubquery($builder, 'count_table')
                ->selectRaw('COUNT(' . $column . ') AS count_value');
        } else {
            $builder = $builder->selectRaw('COUNT(' . $column . ') AS count_value');
        }

        return $this->testMode ? $builder->sql() : (int) ($builder->value('count_value') ?? 0);
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
     * 
     * @param string $type
     * @param string $column
     * @return float|string
     */
    public function aggregate(string $type, string $column)
    {
        $alias = $type . '_value';
        $column = $this->buildColumnName($column);

        $result = $this->clone()->selectRaw(sprintf('%s(%s) AS %s', strtoupper($type), $column, $alias));
        
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
    public function insertUsing(array $columns, Closure|BuilderInterface $query)
    {
        $this->crud = 'insert';

        if ($query instanceof Closure) {
            $builder = $this->newQuery();
            $query($builder);
            $query = $builder;
        }

        $this->columns = $columns;
        $this->values = ['query' => $query];

        if ($this->testMode) {
            return $this->compiler->compileInsertUsing($this);
        }

        $result = $this->execute();
        
        return $result instanceof BaseResult ? $result->affectedRows() : 0;
    }

    /**
     * Insère et récupère l'ID généré
     */
    public function insertGetId(array $values, ?string $sequence = null): int|string|null
    {
        $this->insert($values);

        return $this->db->lastId($this->getTable());
    }

    /**
     * Insère et récupère l'enregistrement inséré
     */
    public function insertAndGet(array $values): ?object
    {
        $this->insert($values);

        $id = $this->db->lastId($this->getTable());
        
        if ($id === null) {
            return null;
        }

        return $this->clone()->where($this->getKeyName(), $id)->first();
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
    public function selectRaw(string|Expression $expression, array $bindings = [])
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
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean
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
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean
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
            'raw'       => true
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
}