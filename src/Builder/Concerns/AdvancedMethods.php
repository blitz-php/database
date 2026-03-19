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
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Utilities\DateTime\Date;
use Closure;
use DateTimeInterface;

/**
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait AdvancedMethods
{
    /**
     * Liste des requêtes UNION
     */
    protected array $unions = [];

    /*
    |--------------------------------------------------------------------------
    | DATE QUERIES
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une clause WHERE pour les dates
     *
     * @param mixed|null $operator
     * @param mixed|null $value
     */
    public function whereDate(array|string $column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            return $this->whereArray($column, $operator ?? $boolean, false, 'whereDate');
        }

        [$column, $operator, $value] = $this->normalizeWhereParameters($column, $operator, $value);

        if (is_int($value)) {
            $value = Date::createFromTimestamp($value);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return $this->whereRaw("DATE({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT DATE
     *
     * @param mixed|null $operator
     * @param mixed|null $value
     */
    public function whereNotDate(array|string $column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            return $this->whereArray($column, $operator ?? $boolean, true, 'whereDate');
        }

        [$column, $operator, $value] = $this->normalizeWhereParameters($column, $operator, $value);

        $operator = $this->invertOperator($operator);

        return $this->whereDate($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE DATE avec OR
     *
     * @param mixed|null $operator
     * @param mixed|null $value
     */
    public function orWhereDate(array|string $column, $operator = null, $value = null): static
    {
        return $this->whereDate($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT DATE avec OR
     *
     * @param mixed|null $operator
     * @param mixed|null $value
     */
    public function orWhereNotDate(array|string $column, $operator = null, $value = null): static
    {
        return $this->whereNotDate($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour les heures
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereTime(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i:s');
        }

        return $this->whereRaw("TIME({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT TIME
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereNotTime(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        $operator = $this->invertOperator($operator);

        return $this->whereTime($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE TIME avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereTime(string $column, $operator, $value = null): static
    {
        return $this->whereTime($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT TIME avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereNotTime(string $column, $operator, $value = null): static
    {
        return $this->whereNotTime($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour le jour
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereDay(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereRaw("DAY({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT DAY
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereNotDay(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        $operator = $this->invertOperator($operator);

        return $this->whereDay($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE DAY avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereDay(string $column, $operator, $value = null): static
    {
        return $this->whereDay($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT DAY avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereNotDay(string $column, $operator, $value = null): static
    {
        return $this->whereNotDay($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour le mois
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereMonth(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereRaw("MONTH({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT MONTH
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereNotMonth(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        $operator = $this->invertOperator($operator);

        return $this->whereMonth($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE MONTH avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereMonth(string $column, $operator, $value = null): static
    {
        return $this->whereMonth($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT MONTH avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereNotMonth(string $column, $operator, $value = null): static
    {
        return $this->whereNotMonth($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour l'année
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereYear(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereRaw("YEAR({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT YEAR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereNotYear(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        $operator = $this->invertOperator($operator);

        return $this->whereYear($column, $operator, $value, $boolean);
    }

    /**
     * Ajoute une clause WHERE YEAR avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereYear(string $column, $operator, $value = null): static
    {
        return $this->whereYear($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE NOT YEAR avec OR
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function orWhereNotYear(string $column, $operator, $value = null): static
    {
        return $this->whereNotYear($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour la semaine
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereWeek(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereRaw("WEEK({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE pour le jour de la semaine (0-6)
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereDayOfWeek(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        $function = $this->db->getDriver() === 'pgsql' ? 'EXTRACT(DOW FROM ' : 'DAYOFWEEK(';

        return $this->whereRaw($function . $column . ') ' . $operator . ' ?', [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE pour le trimestre
     *
     * @param mixed      $operator
     * @param mixed|null $value
     */
    public function whereQuarter(string $column, $operator, $value = null, string $boolean = 'and'): static
    {
        if ($value === null) {
            $value    = $operator;
            $operator = '=';
        }

        return $this->whereRaw("QUARTER({$column}) {$operator} ?", [$value], $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates dans le passé
     */
    public function wherePast(string $column, string $boolean = 'and'): static
    {
        return $this->where($column, '<', Date::now(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates dans le futur
     */
    public function whereFuture(string $column, string $boolean = 'and'): static
    {
        return $this->where($column, '>', Date::now(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates maintenant ou dans le passé
     */
    public function whereNowOrPast(string $column, string $boolean = 'and'): static
    {
        return $this->where($column, '<=', Date::now(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour la date d'aujourd'hui
     */
    public function whereToday(string $column, string $boolean = 'and', bool $not = false): static
    {
        return $this->whereDate($column, $not ? '!=' : '=', Date::today(), $boolean);
    }

    /**
     * Ajoute une clause WHERE NOT TODAY
     */
    public function whereNotToday(string $column, string $boolean = 'and'): static
    {
        return $this->whereToday($column, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE OR TODAY
     */
    public function orWhereToday(string $column): static
    {
        return $this->whereToday($column, 'or');
    }

    /**
     * Ajoute une clause WHERE OR NOT TODAY
     */
    public function orWhereNotToday(string $column): static
    {
        return $this->whereNotToday($column, 'or');
    }

    /**
     * Ajoute une clause WHERE pour les dates avant aujourd'hui
     */
    public function whereBeforeToday(string $column, string $boolean = 'and'): static
    {
        return $this->whereDate($column, '<', Date::today(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates après aujourd'hui
     */
    public function whereAfterToday(string $column, string $boolean = 'and'): static
    {
        return $this->whereDate($column, '>', Date::today(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates aujourd'hui ou avant
     */
    public function whereTodayOrBefore(string $column, string $boolean = 'and'): static
    {
        return $this->whereDate($column, '<=', Date::today(), $boolean);
    }

    /**
     * Ajoute une clause WHERE pour les dates aujourd'hui ou après
     */
    public function whereTodayOrAfter(string $column, string $boolean = 'and'): static
    {
        return $this->whereDate($column, '>=', Date::today(), $boolean);
    }

    /*
    |--------------------------------------------------------------------------
    | JSON QUERIES
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une clause WHERE pour les colonnes JSON
     *
     * @param mixed $value
     */
    public function whereJsonContains(string $column, $value, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type'     => 'json',
            'column'   => $column,
            'value'    => $value,
            'boolean'  => $boolean,
            'not'      => $not,
            'operator' => 'JSON_CONTAINS',
        ];

        $this->bindings->add($value);

        return $this;
    }

    /**
     * Ajoute une clause WHERE JSON NOT CONTAINS
     *
     * @param mixed $value
     */
    public function whereJsonDoesntContain(string $column, $value, string $boolean = 'and'): static
    {
        return $this->whereJsonContains($column, $value, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE OR JSON CONTAINS
     *
     * @param mixed $value
     */
    public function orWhereJsonContains(string $column, $value): static
    {
        return $this->whereJsonContains($column, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE OR JSON NOT CONTAINS
     *
     * @param mixed $value
     */
    public function orWhereJsonDoesntContain(string $column, $value): static
    {
        return $this->whereJsonDoesntContain($column, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE JSON CONTAINS KEY
     */
    public function whereJsonContainsKey(string $column, string $boolean = 'and', bool $not = false): static
    {
        $operator = $not ? 'JSON_NOT_CONTAINS_KEY' : 'JSON_CONTAINS_KEY';

        $this->wheres[] = [
            'type'     => 'jsonkey',
            'column'   => $column,
            'boolean'  => $boolean,
            'not'      => $not,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE JSON NOT CONTAINS KEY
     */
    public function whereJsonDoesntContainKey(string $column, string $boolean = 'and'): static
    {
        return $this->whereJsonContainsKey($column, $boolean, true);
    }

    /**
     * Ajoute une clause WHERE OR JSON CONTAINS KEY
     */
    public function orWhereJsonContainsKey(string $column): static
    {
        return $this->whereJsonContainsKey($column, 'or');
    }

    /**
     * Ajoute une clause WHERE OR JSON NOT CONTAINS KEY
     */
    public function orWhereJsonDoesntContainKey(string $column): static
    {
        return $this->whereJsonDoesntContainKey($column, 'or');
    }

    /**
     * Ajoute une clause WHERE pour la longueur JSON
     */
    public function whereJsonLength(string $column, string $operator, int $value, string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type'     => 'jsonlength',
            'column'   => $column,
            'value'    => $value,
            'operator' => $operator,
            'boolean'  => $boolean,
            'json_op'  => 'JSON_LENGTH',
        ];

        $this->bindings->add($value);

        return $this;
    }

    /**
     * Ajoute une clause WHERE OR JSON LENGTH
     */
    public function orWhereJsonLength(string $column, string $operator, int $value): static
    {
        return $this->whereJsonLength($column, $operator, $value, 'or');
    }

    /**
     * Ajoute une clause WHERE pour la recherche dans JSON (MySQL)
     */
    public function whereJsonSearch(string $column, string $value, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type'    => 'jsonsearch',
            'column'  => $column,
            'value'   => $value,
            'boolean' => $boolean,
            'not'     => $not,
        ];

        $this->bindings->add($value);

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | UNION QUERIES
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une requête UNION
     */
    public function union(BuilderInterface|Closure $query, bool $all = false): static
    {
        $this->unions[] = [
            'query' => $this->createUnionQuery($query),
            'all'   => $all,
        ];

        return $this;
    }

    /**
     * Ajoute une requête UNION ALL
     */
    public function unionAll(BuilderInterface|Closure $query): static
    {
        return $this->union($query, true);
    }

    /**
     * Crée une requête pour UNION
     */
    protected function createUnionQuery(BaseBuilder|Closure $query): BaseBuilder
    {
        if ($query instanceof Closure) {
            $builder = $this->newQuery();
            $query($builder);

            $this->bindings->merge($builder->bindings);

            return $builder;
        }

        $this->bindings->merge($query->bindings);

        return $query;
    }
}
